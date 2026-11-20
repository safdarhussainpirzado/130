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
    printf '%s' "$2" > "secrets/$1"; chmod 444 "secrets/$1"  # container runs as www-data; host access is restricted by the 700 secrets/ dir
    echo "create secrets/$1 (len=$(printf '%s' "$2" | wc -c))"
}
mk app_key "$(get APP_KEY)"
mk db_password "$(get DB_PASSWORD)"
# Compose default for MinIO is admin/password; keep the current effective value.
AWS="$(get AWS_SECRET_ACCESS_KEY)"; mk aws_secret_access_key "${AWS:-password}"

# Push Notifications (Desk Scope)
VAPID_KEY="$(get VAPID_PRIVATE_KEY || true)"
if [ -n "$VAPID_KEY" ]; then
    mk vapid_private_key "$VAPID_KEY"
elif [ ! -s "secrets/vapid_private_key" ]; then
    echo "note   secrets/vapid_private_key (generate via: php artisan push:generate-vapid)"
fi

if [ ! -s "secrets/firebase_credentials.json" ]; then
    echo "note   secrets/firebase_credentials.json (drop service account JSON here with mode 600)"
fi
