#!/bin/sh
set -e

echo "🔔 Booting NHMP 130 CRM container..."

cd /var/www/html

# ── Docker Secrets Resolution ───────────────────────────────────────────────
# Injects secrets from /run/secrets/ into environment variables dynamically
if [ -d "/run/secrets" ]; then
    echo "🔒 Resolving Docker Secrets from /run/secrets..."
    for secret in /run/secrets/*; do
        if [ -f "$secret" ]; then
            var_name=$(basename "$secret" | tr '[:lower:]' '[:upper:]')
            val=$(cat "$secret")
            export "$var_name"="$val"
        fi
    done
fi

# Ensure essential directories exist
mkdir -p storage/framework/sessions \
         storage/framework/views \
         storage/framework/cache/data \
         storage/logs \
         bootstrap/cache
# ── Frontend Asset Release & Retention ─────────────────────────────────────
# Publish Vite manifest and assets from image cache to public volume.
# 1. Versioned assets are published BEFORE updating manifest.json.
# 2. Older hashed assets are retained (non-destructive) for rolling transitions.
# 3. Uses flock and atomic file rename to prevent concurrent startup race conditions.
if [ -d "/var/www/build_cache" ]; then
    echo "📦 Publishing frontend build assets to public volume..."
    mkdir -p public/build/assets 2>/dev/null || true
    (
        flock -x 200
        # Step A: Publish new/updated versioned assets non-destructively
        cp -rn /var/www/build_cache/assets/* public/build/assets/ 2>/dev/null || cp -rf /var/www/build_cache/assets/* public/build/assets/ 2>/dev/null || true
        # Step B: Write manifest atomically via temporary file to prevent partial reads
        cp -f /var/www/build_cache/manifest.json public/build/manifest.json.tmp 2>/dev/null || true
        mv -f public/build/manifest.json.tmp public/build/manifest.json 2>/dev/null || true
        # Step C: Enforce safe read-only serving permissions
        find public/build -type d -exec chmod 755 {} + 2>/dev/null || true
        find public/build -type f -exec chmod 644 {} + 2>/dev/null || true
    ) 200>/tmp/.asset_publish.lock || true
fi

# Clear anything cached wrongly during build transport
# Create an empty packages.php to satisfy Laravel if needed (though usually not necessary)
# ── Database migrations ─────────────────────────────────────────────────────
# IMPORTANT: Migrations are NOT run automatically on every boot.
# Run manually during deployment with:
#   docker exec crm_app php artisan migrate --force
#
# To check pending migrations without applying:
#   docker exec crm_app php artisan migrate:status
#
# For CI/CD pipelines, inject RUN_MIGRATIONS=true as an env var to auto-run:
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "🔔 Running DB Migrations (RUN_MIGRATIONS=true)..."
    php artisan migrate --force
else
    echo "ℹ️  Skipping migrations (set RUN_MIGRATIONS=true to run on boot)"
fi

# Clear caches now that DB is ready
php artisan optimize:clear || echo "⚠️  Could not clear caches (DB might not be ready)"

echo "🔔 Caching Application State for JIT & Highest Performance..."
php artisan config:cache || echo "⚠️  Failed to cache config"
php artisan event:cache || echo "⚠️  Failed to cache events"
php artisan route:cache || echo "⚠️  Failed to cache routes"
php artisan view:cache || echo "⚠️  Failed to cache views"

if [ "$1" = "rr" ] || [ "${APP_SERVER}" = "roadrunner" ] || [ "${APP_SERVER}" = "octane" ] || [ "$1" = "php-fpm" ] || [ -z "$1" ]; then
    echo "⚡ Starting High-Concurrency RoadRunner / Octane HTTP server on port 8000..."
    exec rr serve -c .rr.yaml
fi

echo "🔔 Executing CLI command: $@"
exec "$@"
