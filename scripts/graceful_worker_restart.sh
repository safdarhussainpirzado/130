#!/bin/bash
set -euo pipefail

# ==============================================================================
# NHMP 130 CRM — Graceful Queue Worker Recycling
# ==============================================================================
# Signals Laravel queue workers to finish their current emergency job before
# exiting, and instructs Supervisor to restart them cleanly.
# ==============================================================================

CONTAINER_NAME="${1:-130-app-1-1}"

echo "🔄 Initiating graceful worker restart on container: $CONTAINER_NAME..."

# Step 1: Issue artisan queue:restart to notify workers to terminate after current job
docker exec "$CONTAINER_NAME" php /var/www/html/artisan queue:restart

# Step 2: Allow brief grace period for in-flight jobs (configured stopwaitsecs in Supervisor handles limits)
sleep 2

# Step 3: Check Supervisor status
if docker exec "$CONTAINER_NAME" which supervisorctl >/dev/null 2>&1; then
    echo "📋 Active Supervisor Workers on $CONTAINER_NAME:"
    docker exec "$CONTAINER_NAME" supervisorctl status || true
else
    echo "ℹ️  Supervisor not directly running as PID 1 in $CONTAINER_NAME (handled per container pool)."
fi

echo "✅ Graceful worker restart signal propagated successfully."
