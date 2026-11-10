# NHMP 130 performance engineering — measured findings & applied changes (2026-11-10)

## Hardware (measured)
VMware VM, 16 vCPU (Xeon Gold 6252N @2.3 GHz, 1 NUMA node), 32 GB RAM, 8 GB swap, 500 GB virtual disk (ext4/LVM,
mq-deadline), 10 Gb/s NIC. **No GPUs** (only VMware SVGA); GPUs would not help Laravel/PHP/MySQL regardless.
Idle: 88-95% CPU idle, 16 GB RAM available, MySQL Max_used_connections 9/150.

## Measured findings
- DB is 770 MB total (calls 349 MB incl. indexes). Buffer-pool hit ratio 99.9999%. 8 GB pool is not justified.
- 56.9M connections in 126 days (one per PHP-FPM request): SET NAMES+USE cost ~10,700 s cumulative -> Octane persistent workers.
- flush_log_at_trx_commit=2 / sync_binlog=0 weakened durability; fsync is 1.5 ms and commits ~0.1/s, so full durability is ~free.
- Sort_merge_passes 1.86M (sort_buffer_size 256K). No slow *application* queries; "slow" digests are mysqldump.
- db-2/db-3 are live GTID replicas (identical row counts) - left untouched.
- Redis: noeviction (good), no maxmemory, no AOF.
- Host swappiness 60 with 3.2 GB swapped while 16 GB RAM available.

## Applied (all dynamic, no restarts; backups: /root/deploy_backups/*_enc verified)
| Change | Before | After | Rollback |
|---|---|---|---|
| MySQL innodb_flush_log_at_trx_commit | 2 | 1 | SET PERSIST ... =2 |
| MySQL sync_binlog | 0 | 1 | SET PERSIST sync_binlog=0 |
| MySQL sort_buffer_size | 256K | 2M | SET PERSIST sort_buffer_size=262144 |
| MySQL innodb_buffer_pool_size | 512M | 1536M (online resize) | SET PERSIST ...=536870912 |
| Redis maxmemory / AOF | none / off | 2gb noeviction / everysec | CONFIG SET |
| Host vm.swappiness | 60 | 10 (/etc/sysctl.d/99-nhmp-tuning.conf) | sysctl -w vm.swappiness=60 |
Also: Octane canary (web-octane-1, weight 1 vs 20/20) enabled in HAProxy; Docker secrets; encrypted backups.

## Not done (needs approval / separate staging)
Load tests at 100-1000 concurrent users, PHP-FPM re-tuning (superseded by Octane), queue worker topology, HTTP/3,
dashboard pre-aggregation, Grafana alerting. No load test is run against the live helpline without explicit approval.

## Security hardening (2026-11-10)
- DOCKER-USER firewall (scripts: `nhmp-firewall.sh`, repo `scripts/`): MySQL 3306/3309/3310/3311, Redis 6379, MinIO 9000
  reachable only from Docker, loopback, Tailscale, WireGuard. Verified with a synthetic external source (blocked) and
  internal app->db/redis/minio (OK). NOT yet persistent across reboot (needs the systemd unit in docs below).
- HAProxy stats password rotated (file /root/.haproxy_stats_password on prod, root-only). Repo copy has a placeholder:
  do NOT rsync haproxy/haproxy.cfg over prod.
- Plaintext backups encrypted copies in /root/archived_backups (verified byte-identical); plaintext originals still present.
- Pending decisions: MySQL root pw (Group Replication recovery uses root@%), MinIO root creds (bucket empty), Grafana admin:admin,
  systemd unit for firewall persistence, deletion of plaintext backups.

## MySQL root rotation (2026-11-10)
- Group Replication recovery on all 3 nodes now uses dedicated `repl_gr` (TLS required, recovery_use_ssl=ON persisted).
  db-2 and db-3 rejoin-tested (ONLINE, recovery via db-1 donor, no auth errors). db-1 (primary) configured + persisted but
  its rejoin path is untested (would need a failover).
- Least-privilege `backup_user@localhost` (SELECT, SHOW VIEW, TRIGGER, EVENT, SHOW_ROUTINE) used by scripts/backup_encrypted.sh.
- `root@localhost` and `root@%` rotated (new value only in /root/.mysql_root_password on prod, 0400). Old default rejected on all nodes.
- Containers still carry the OLD MYSQL_ROOT_PASSWORD env (set at creation). Do not use `$MYSQL_ROOT_PASSWORD` inside them;
  use `docker exec -e MYSQL_PWD="$(cat /root/.mysql_root_password)" ... mysql -uroot`.
  A future recreate picks up the new value from .env. Container health (mysqladmin ping) is unaffected.
- Rollback: ALTER USER 'root'@'localhost'/'%' back to the previous default; repl_gr stays valid regardless.

## Octane cutover (2026-11-10, ~20:20-20:40 PKT) — LIVE
- Production now served by Laravel Octane (RoadRunner) on app-octane-1/2 behind web-octane-1/2 (nginx). 12 workers/node,
  exec_ttl 300s (report exports), max-requests 500. Migrations: none pending for this code (verified `migrate --pretend`).
- php-fpm nodes (app-1/2, web-1/2) kept RUNNING as `backup` servers in HAProxy (take traffic only if both Octane nodes are down).
  Rollback: `set server app_nodes/web-octane-1 state maint` + same for -2 (backup nodes then serve); or edit cfg and reload.
- Ramp: 50/50 new sessions -> web-1 maint -> web-2 maint -> config made permanent. 0 5xx, 0 app exceptions throughout.
- Known: /calls returns a few 400s on BOTH runtimes (app behaviour, pre-existing).
