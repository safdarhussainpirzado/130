#!/usr/bin/env bash
set -euo pipefail

# NHMP 130 Helpline — Ransomware-Proof Asymmetric Backup Engine
# Encrypts database dumps with an RSA-4096 Public Key.
# Even with full root access on this server, backups CANNOT be decrypted
# because the private key is held strictly offline.

BACKUP_DIR="${BACKUP_DIR:-./backups/asymmetric}"
PUBLIC_KEY="${PUBLIC_KEY:-./storage/keys/backup_public.pem}"
DB_NAME="${DB_NAME:-130_crm_db}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-secret}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3309}"

mkdir -p "$BACKUP_DIR"

if [ ! -f "$PUBLIC_KEY" ]; then
    echo "❌ Error: Public key not found at $PUBLIC_KEY"
    exit 1
fi

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
TEMP_DIR=$(mktemp -d -t nhmp-backup-XXXXXX)
trap 'rm -rf "$TEMP_DIR"' EXIT

SESSION_KEY="$TEMP_DIR/session.key"
ENCRYPTED_SESSION_KEY="$TEMP_DIR/session.key.enc"
RAW_DUMP="$TEMP_DIR/dump.sql.gz"
ENCRYPTED_DUMP="$TEMP_DIR/dump.sql.gz.enc"
FINAL_ARCHIVE="$BACKUP_DIR/130_crm_${TIMESTAMP}.nhmp.enc.tar"

echo "🔐 [1/5] Generating ephemeral 256-bit symmetric session key..."
openssl rand -out "$SESSION_KEY" 32

echo "📦 [2/5] Creating MySQL compressed database dump..."
if [ -n "${DB_CONTAINER:-}" ]; then
    docker exec "$DB_CONTAINER" mysqldump --single-transaction --quick --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip -c > "$RAW_DUMP"
else
    # Auto-detect container or localhost
    if docker ps --format '{{.Names}}' | grep -q '130-db-1-1'; then
        docker exec 130-db-1-1 mysqldump --single-transaction --quick --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip -c > "$RAW_DUMP"
    else
        mysqldump -h "$DB_HOST" -P "$DB_PORT" --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip -c > "$RAW_DUMP"
    fi
fi

DUMP_SIZE=$(stat -c%s "$RAW_DUMP" 2>/dev/null || stat -f%z "$RAW_DUMP")
echo "   Compressed dump size: $((DUMP_SIZE / 1024 / 1024)) MB"

echo "🔒 [3/5] Encrypting database dump with AES-256-CBC..."
openssl enc -aes-256-cbc -salt -pbkdf2 -iter 100000 -in "$RAW_DUMP" -out "$ENCRYPTED_DUMP" -pass "file:$SESSION_KEY"

echo "🛡️  [4/5] Sealing session key with RSA-4096 Public Key..."
openssl pkeyutl -encrypt -pubin -inkey "$PUBLIC_KEY" -in "$SESSION_KEY" -out "$ENCRYPTED_SESSION_KEY"

# Securely wipe plaintext session key and raw dump from memory/disk
shred -u "$SESSION_KEY" 2>/dev/null || rm -f "$SESSION_KEY"
shred -u "$RAW_DUMP" 2>/dev/null || rm -f "$RAW_DUMP"

echo "📦 [5/5] Packaging sealed asymmetric backup bundle..."
tar -cf "$FINAL_ARCHIVE" -C "$TEMP_DIR" session.key.enc dump.sql.gz.enc

ARCHIVE_SIZE=$(stat -c%s "$FINAL_ARCHIVE" 2>/dev/null || stat -f%z "$FINAL_ARCHIVE")
ARCHIVE_SHA=$(sha256sum "$FINAL_ARCHIVE" | awk '{print $1}')

# Audit trail
mkdir -p storage/logs
echo "{\"timestamp\":\"$(date --iso-8601=seconds)\",\"backup_file\":\"$FINAL_ARCHIVE\",\"size_bytes\":$ARCHIVE_SIZE,\"sha256\":\"$ARCHIVE_SHA\",\"asymmetric_key\":\"$PUBLIC_KEY\"}" >> storage/logs/backup_audit.log

echo ""
echo "✅ ASYMMETRIC RANSOMWARE-PROOF BACKUP COMPLETE:"
echo "   Archive: $FINAL_ARCHIVE"
echo "   Size:    $((ARCHIVE_SIZE / 1024 / 1024)) MB"
echo "   SHA-256: $ARCHIVE_SHA"
echo "   Security: Can ONLY be decrypted with offline private key."
