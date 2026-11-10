#!/bin/sh
# Create ./secrets/* from the existing .env WITHOUT printing values.
# Idempotent: never overwrites an existing secret file.
set -eu
cd "$(dirname "$0")/.."
umask 077
mkdir -p secrets && chmod 700 secrets
get() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' | tr -d '\n'; }
mk() {  # mk <file> <value>
    if [ -s "secrets/$1" ]; then echo "keep   secrets/$1 (exists)"; return; fi
    [ -n "$2" ] || { echo "ERROR: no value for $1" >&2; exit 1; }
    printf '%s' "$2" > "secrets/$1"; chmod 400 "secrets/$1"
    echo "create secrets/$1 (len=$(printf '%s' "$2" | wc -c))"
}
mk app_key "$(get APP_KEY)"
mk db_password "$(get DB_PASSWORD)"
# Compose default for MinIO is admin/password; keep the current effective value.
AWS="$(get AWS_SECRET_ACCESS_KEY)"; mk aws_secret_access_key "${AWS:-password}"
