#!/usr/bin/env python3
"""
Staging Reconciliation and Evidence Collector
Collects rigorous, verifiable evidence from staging host root@100.108.239.107.
"""
import subprocess
import json
import sys

SSH_TARGET = "root@100.108.239.107"

def ssh(cmd, timeout=30):
    full_cmd = f"ssh -n -o ConnectTimeout=5 -o BatchMode=yes {SSH_TARGET} {json.dumps(cmd)} < /dev/null"
    res = subprocess.run(full_cmd, shell=True, capture_output=True, text=True, timeout=timeout)
    if res.returncode != 0:
        print(f"ERROR executing [{cmd}]: {res.stderr.strip()}", file=sys.stderr)
    return res.stdout.strip()

def run_local(cmd):
    res = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    return res.stdout.strip()

def main():
    print("=== 1. HOST & SYSTEM IDENTITY ===")
    print("Hostname:", ssh("hostname"))
    print("Kernel:", ssh("uname -a"))
    print("Uptime:", ssh("uptime"))
    print("CPUs:", ssh("nproc"))
    print("Memory:\n" + ssh("free -h"))
    print("Disk:\n" + ssh("df -h /"))
    print("IP Addresses:\n" + ssh("ip -br addr"))
    print("Routes:\n" + ssh("ip route"))

    print("\n=== 2. GIT WORKING TREE (STAGING) ===")
    print("Repo path: /home/mrpirzado/projects/130")
    print("Current Branch:", ssh("cd /home/mrpirzado/projects/130 && git branch --show-current"))
    print("Commit Hash:", ssh("cd /home/mrpirzado/projects/130 && git rev-parse HEAD"))
    print("Commit Message:", ssh("cd /home/mrpirzado/projects/130 && git log -1 --pretty=format:'%h - %an: %s (%ci)'"))
    print("Status:\n" + ssh("cd /home/mrpirzado/projects/130 && git status -s"))
    print("Last 5 Commits:\n" + ssh("cd /home/mrpirzado/projects/130 && git log -n 5 --oneline"))

    print("\n=== 3. RUNNING CONTAINERS & HEALTH ===")
    containers_json = ssh("docker ps -a --format '{{json .}}'")
    containers = [json.loads(line) for line in containers_json.splitlines() if line]
    for c in containers:
        if "130-" in c.get("Names", ""):
            print(f"- {c['Names']}: Image={c['Image']}, Status={c['Status']}, Ports={c['Ports']}")

    print("\n=== 4. CONTAINER IMAGE IDS & INSPECTION ===")
    img_info = ssh("docker inspect 130-app-1-1 130-web-1-1 130-db-1-1 130-redis-1 130-haproxy-1 --format '{{.Name}}: Image={{.Image}} Created={{.Created}} State={{.State.Status}}'")
    print(img_info)

    print("\n=== 5. CONTAINER MOUNT TOPOLOGY ===")
    mounts = ssh("docker inspect 130-app-1-1 130-app-2-1 130-web-1-1 130-web-2-1 --format '{{.Name}}: Mounts={{range .Mounts}}{{.Source}}->{{.Destination}} ({{.Type}}) {{end}}'")
    print(mounts)

    print("\n=== 6. CONTAINER WRITABLE LAYER AUDIT (docker diff) ===")
    for cname in ["130-app-1-1", "130-app-2-1", "130-web-1-1", "130-haproxy-1"]:
        diff_out = ssh(f"docker diff {cname} | grep -E 'var/www|etc/nginx|usr/local/etc' | head -n 20")
        print(f"Diff for {cname}:\n{diff_out if diff_out else '  (clean / no app modifications)'}")

    print("\n=== 7. DATABASE STATUS & ISOLATION CHECK ===")
    db_status = ssh("docker exec 130-db-1-1 mysql -u root -p'root' -e \"SELECT @@hostname, @@port, @@version, @@read_only;\" 2>&1")
    print("MySQL Server Info:\n" + db_status)
    gr_status = ssh("docker exec 130-db-1-1 mysql -u root -p'root' -e \"SELECT * FROM performance_schema.replication_group_members;\" 2>&1")
    print("Group Replication Members:\n" + gr_status)
    tbl_counts = ssh("docker exec 130-db-1-1 mysql -u root -p'root' 130_crm_db -e \"SELECT 'calls' tbl, COUNT(*) cnt, MAX(created_at) latest FROM calls UNION ALL SELECT 'call_sequences', COUNT(*), MAX(current_seq) FROM call_sequences UNION ALL SELECT 'users', COUNT(*), MAX(created_at) FROM users UNION ALL SELECT 'login_logs', COUNT(*), MAX(created_at) FROM login_logs;\" 2>&1")
    print("Table Counts & High-water mark:\n" + tbl_counts)

    print("\n=== 8. REDIS ISOLATION & QUEUES ===")
    redis_info = ssh("docker exec 130-redis-1 redis-cli INFO replication 2>&1")
    print("Redis Replication Info:\n" + redis_info)
    redis_keys = ssh("docker exec 130-redis-1 redis-cli KEYS 'queues:*' 2>&1")
    print("Redis Queue Keys:\n" + redis_keys)
    redis_dbsize = ssh("docker exec 130-redis-1 redis-cli DBSIZE 2>&1")
    print("Redis Total Keys:", redis_dbsize)

    print("\n=== 9. BACKUP VERIFICATION ===")
    backups = ssh("ls -lh /home/mrpirzado/projects/130/backups/ 2>&1")
    print("Backups Directory:\n" + backups)
    gzip_test = ssh("gzip -t /home/mrpirzado/projects/130/backups/staging_baseline_20261029.sql.gz && echo 'BACKUP INTEGRITY: OK' || echo 'BACKUP INTEGRITY: FAILED'")
    print(gzip_test)

if __name__ == "__main__":
    main()
