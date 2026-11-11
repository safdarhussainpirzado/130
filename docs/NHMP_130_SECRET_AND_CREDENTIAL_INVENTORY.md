# NHMP 130 Helpline — Complete Secret and Credential Inventory

**Document ID:** `NHMP-130-SEC-INV-2026-V1`  
**Classification:** Controlled Security Artifact / Confidential Credential Registry  
**Environment:** Staging (`DESKTOP-BU2GH1K`) & Candidate Data Center Architecture  
**Standard Reference:** NIST SP 800-53 Rev. 5 (IA-5 Authenticator Management, SC-12 Cryptographic Key Establishment)  
**Last Audited:** November 05, 2026  

---

## 1. Inventory & Consumer Mapping Table

| Secret ID | Secret Type | Consumer Container / Process | Delivery Mechanism | Owner | Scope & Privileges Granted | Expiry / Rotation Policy | Active Version | Exposure Assessment | Remediation & Isolation Status | Verification Test |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :---: | :--- | :--- | :--- |
| **SEC-01-APPKEY** | Symmetric Master Key | `130-app-1-1`, `130-app-2-1` | Docker Secret (`/run/secrets/app_key`) | Platform Security | Session signing, cookie encryption, legacy AES-CBC decrypt | 180 Days | `v2.0` | Absent in Git; decoupled from `.env` | Mounted as tmpfs read-only `0400` | Verified via `docker_secret('app_key')` in test S06 |
| **SEC-02-BIDX** | Cryptographic Salt | `130-app-1-1`, `130-app-2-1` | Docker Secret (`/run/secrets/bidx_salt`) | Data Security | Deterministic HMAC-SHA256 blind indexing for phone numbers | 365 Days | `v1.0` | Absent in Git; decoupled from `.env` | Isolated salt, distinct from APP_KEY and AUDIT_SALT | Verified via `phone_blind_index()` in Gate 2 / S06 |
| **SEC-03-AUDIT** | Cryptographic Salt | `130-app-1-1`, `130-app-2-1` | Docker Secret (`/run/secrets/audit_salt`) | SecOps / Audit | HMAC-SHA256 hash chaining of `audit_logs` entries | 365 Days | `v1.0` | Absent in Git; decoupled from `.env` | Mounted independently into app containers | Verified via `AuditLog::booted()` in Gate 8 / S06 |
| **SEC-04-DBPASS** | Database Credential | `130-app-1-1`, `130-app-2-1`, `130-db-1-1` | Docker Secret (`/run/secrets/db_password`) | DBA / App Eng | `crm_user` DML privileges on `130_crm_db` | 90 Days | `v1.0` | Decoupled from `.env` | Dedicated application user; no administrative grants | Verified via PDO MySQL connection in S12 / S19 |
| **SEC-05-DBROOT** | Database Root Password | `130-db-1-1`, `130-db-2-1`, `130-db-3-1`, `mysql-exporter` | Docker Secret (`/run/secrets/db_root_password`) | DBA Team | Full MySQL administrative ownership (`ALL PRIVILEGES`) | 90 Days | `v1.0` | **REMEDIATED**: Previously mounted into app containers | **Removed from `app-1` and `app-2`** in `docker-compose.yml` | Verified absent from app container `/run/secrets/` |
| **SEC-06-REDIS** | In-Memory Auth Token | `130-app-1-1`, `130-app-2-1`, `130-redis-1`, `redis-exporter` | Docker Secret (`/run/secrets/redis_password`) | Platform Eng | `--requirepass` authentication on Redis port 6379 | 90 Days | `v1.0` | Decoupled from `.env` | Application authenticates via `REDIS_PASSWORD` secret | Verified direct unauthenticated TCP rejected (`NOAUTH`) in S11 |
| **SEC-07-MINIO** | S3 Secret Access Key | `130-app-1-1`, `130-app-2-1`, `130-minio-1`, `createbuckets` | Docker Secret (`/run/secrets/minio_root_password`) | Storage Eng | S3 API read/write on bucket `crm-storage` | 180 Days | `v1.0` | Decoupled from `.env` | Anonymous S3 access disabled; authenticated only | Verified via `config/filesystems.php` S3 driver |
| **SEC-08-HAPROXY**| Proxy Stats Password | `130-haproxy-1` | Docker Secret (`/run/secrets/haproxy_stats_password`) | Network Eng | Access to HAProxy stats dashboard on port 8404 | 90 Days | `v1.0` | Decoupled from `.env` | Stats port bound strictly to loopback `127.0.0.1:8404` | Verified authenticated stats access via curl |
| **SEC-09-GRAFANA**| Admin Web Password | `130-grafana-1` | Docker Secret (`/run/secrets/grafana_admin_password`) | Monitoring | Grafana administrator dashboard login | 90 Days | `v1.0` | Decoupled from `.env` | Port bound to loopback `127.0.0.1:3000` | Verified via Grafana authenticated HTTP check |
| **SEC-10-BKPPUB** | RSA-4096 Public Key | `130-app-1-1`, `130-app-2-1`, Backup Scripts | File Mount (`storage/keys/backup_public.pem`) | Backup Admin | Asymmetric sealing of database dumps (`openssl pkeyutl`) | Permanent | `v1.0` | Safe for server deployment; public key only | Deployed to server; private key strictly offline | Verified RSA-4096 sealing test in S10 |
| **SEC-11-BKPPRI** | RSA-4096 Private Key| **OFFLINE AIR-GAPPED SAFE ONLY** | Offline Physical Media | CISO / Director IT | Unsealing and decrypting database backup archives | Permanent | `v1.0` | **CONFIRMED OFFLINE**: Never deployed to server | Verified strictly absent from host and container disks | Verified file absence check in S16 |

---

## 2. Secrets Storage & Boundary Rules

1. **Storage Location on Host:** Stored in directory [docker/secrets/](file:///home/mrpirzado/projects/130/docker/secrets/) with permissions `0600` owned by developer user (`1000:1000`).
2. **Git Exclusion:** Verified `.gitignore` explicitly blocks `docker/secrets/`, `*.pem`, `*.key`, and `.env`.
3. **Container Ingress:** Mounted via Docker Compose `secrets:` directive into `/run/secrets/<secret_name>`.
4. **Least-Privilege Mounting Rule:** No container may receive secrets outside its explicit operational role. Specifically, application containers (`app-1`, `app-2`) receive only runtime credentials (`app_key`, `bidx_salt`, `audit_salt`, `db_password`, `redis_password`, `minio_root_password`).
