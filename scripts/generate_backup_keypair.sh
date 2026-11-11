#!/usr/bin/env bash
set -euo pipefail

# NHMP 130 Helpline — Offline Keypair Generator for Ransomware-Proof Backups
# RUN THIS ONLY ON AN AIR-GAPPED / OFFLINE ADMINISTRATIVE WORKSTATION

KEY_DIR="${1:-./storage/keys}"
mkdir -p "$KEY_DIR"

echo "🔐 Generating RSA-4096 Asymmetric Keypair for NHMP 130 Asymmetric Backups..."

# 1. Generate Private Key (STRICTLY AIR-GAPPED - NEVER STORE ON PRODUCTION SERVER)
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -out "$KEY_DIR/backup_private.key"
chmod 400 "$KEY_DIR/backup_private.key"

# 2. Extract Public Key (SAFE TO DEPLOY TO PRODUCTION SERVER)
openssl rsa -pubout -in "$KEY_DIR/backup_private.key" -out "$KEY_DIR/backup_public.pem"
chmod 644 "$KEY_DIR/backup_public.pem"

echo "✅ Keys generated successfully:"
echo "   Public Key  (Server):  $KEY_DIR/backup_public.pem"
echo "   Private Key (OFFLINE): $KEY_DIR/backup_private.key"
echo ""
echo "⚠️  CRITICAL: Move 'backup_private.key' to offline cold storage (e.g. encrypted USB in safe)."
echo "   The production server ONLY needs 'backup_public.pem' to encrypt backups."
