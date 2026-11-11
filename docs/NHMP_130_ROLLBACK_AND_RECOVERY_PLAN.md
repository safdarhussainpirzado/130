# NHMP 130 Helpline — Rollback, Disaster Recovery and Incident Response Plan

**Document ID:** `NHMP-130-SEC-RBK-2026-V1`  
**Classification:** Operational Continuity & Rollback Runbook  
**Standard References:** NIST SP 800-34 Rev. 1 (Contingency Planning Guide for Federal Information Systems), ISO/IEC 27031  
**Effective Date:** November 05, 2026  

---

## 1. Baseline State & Version Freeze

Before applying any security patch or schema modification, the baseline operational state is recorded:

| Parameter | Baseline Value | Rollback Target State |
| :--- | :--- | :--- |
| **Git Baseline Commit** | `7d89960f7be39222191af3c72e2084bfc9cd618f` | Revert working directory to this commit hash |
| **Application Image Tag** | `130-app:latest` (Digest: `sha256:2d4cca69ff93`) | Retain pre-build image ID in local registry |
| **Database Container** | `130-db-1-1` (`mysql:8.4`) | Point-in-Time GTID binlog restore position |
| **Active Cryptographic Cast** | [app/Casts/GracefulEncrypted.php](file:///home/mrpirzado/projects/130/app/Casts/GracefulEncrypted.php) | v2 AES-256-GCM with backward-compatible v1 CBC fallback |

---

## 2. Emergency Rollback Procedures

### 2.1 Cryptographic Rollback (Reverting AES-GCM to Legacy Mode)
- **Scenario:** Application reports decryption errors on unexpected legacy data formatting.
- **Procedure:**
  1. `GracefulEncrypted` inherently supports legacy v1 CBC and raw plaintext reading through its multi-tiered fallback architecture. No database rollback is required for historical records.
  2. If emergency rollback of the PHP cast is required:
     ```bash
     git checkout 7d89960f7be39222191af3c72e2084bfc9cd618f -- app/Casts/GracefulEncrypted.php
     docker cp app/Casts/GracefulEncrypted.php 130-app-1-1:/var/www/html/app/Casts/GracefulEncrypted.php
     docker cp app/Casts/GracefulEncrypted.php 130-app-2-1:/var/www/html/app/Casts/GracefulEncrypted.php
     ```
  3. Verify application health via `curl -k -I https://localhost/up`.

### 2.2 Database Secret Rollback (`docker-compose.yml`)
- **Scenario:** Database connectivity fails following secret decoupling.
- **Procedure:**
  1. Inspect `/run/secrets/db_password` mount permissions on app nodes.
  2. Confirm MySQL user host mapping (`'crm_user'@'%'`).
  3. If Docker Compose configuration requires emergency reversion:
     ```bash
     git checkout 7d89960f7be39222191af3c72e2084bfc9cd618f -- docker-compose.yml
     docker compose up -d --no-deps app-1 app-2
     ```

### 2.3 Database Point-in-Time Recovery (PITR)
- **Scenario:** Data corruption or ransomware attack on active primary MySQL node.
- **Procedure:**
  1. Retrieve the latest sealed asymmetric backup archive from storage: `backups/asymmetric/130_crm_YYYYMMDD_HHMMSS.nhmp.enc.tar`.
  2. Transport archive to the **Air-Gapped Decryption Terminal** containing `backup_private.key`.
  3. Extract and unseal the session key:
     ```bash
     tar -xf 130_crm_*.nhmp.enc.tar
     openssl pkeyutl -decrypt -inkey backup_private.key -in session.key.enc -out session.key
     openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 -in dump.sql.gz.enc -out dump.sql.gz -pass file:session.key
     gunzip dump.sql.gz
     ```
  4. Securely erase the plaintext session key (`shred -u session.key`).
  5. In isolated staging, execute test database restoration using [scripts/test_db_restore.sh](file:///home/mrpirzado/projects/130/scripts/test_db_restore.sh):
     ```bash
     cat dump.sql | docker exec -i 130-db-1-1 mysql -u root -p"$DB_PASS" 130_crm_db
     ```
  6. Verify row count and trigger parity.
