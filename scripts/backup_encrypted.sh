#!/bin/sh
# Encrypted production backup (AES-256, PBKDF2, 600k iterations). Non-blocking: mysqldump
# --single-transaction at low IO priority. Key file must NOT be stored next to the backups.
# Usage: KEY_FILE=/root/.nhmp_backup_key scripts/backup_encrypted.sh [outdir]
set -eu
KEY_FILE="${KEY_FILE:-/root/.nhmp_backup_key}"
OUT="${1:-/root/deploy_backups/$(date +%Y%m%d_%H%M%S)_enc}"
[ -r "$KEY_FILE" ] || { echo "missing key file $KEY_FILE" >&2; exit 1; }
umask 077; mkdir -p "$OUT"
ionice -c3 nice -n19 docker exec 130-db-1-1 sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --routines --triggers --events --set-gtid-purged=OFF --no-tablespaces 130_crm_db' 2>"$OUT/dump.err" \
  | gzip -6 | openssl enc -aes-256-cbc -pbkdf2 -iter 600000 -salt -pass "file:$KEY_FILE" > "$OUT/db.sql.gz.enc"
sha256sum "$OUT/db.sql.gz.enc" > "$OUT/SHA256SUMS"
# Verify: decrypt + gzip integrity + dump trailer
openssl enc -d -aes-256-cbc -pbkdf2 -iter 600000 -pass "file:$KEY_FILE" -in "$OUT/db.sql.gz.enc" | gzip -dc | tail -n 2 | grep -q "Dump completed" && echo "VERIFIED $OUT" || { echo "VERIFY FAILED" >&2; exit 1; }
