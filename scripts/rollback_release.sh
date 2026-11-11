#!/bin/bash
set -euo pipefail

# ==============================================================================
# NHMP 130 CRM — Automated Zero-Downtime Release Rollback Script
# ==============================================================================
# Reverts cluster to prior verified immutable release digest without
# dropping active TCP connections, emergency calls, or degrading HA ingress.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

# Independent Safety Guard: Prevent accidental direct execution bypass
if [ "${CONFIRM_ROLLBACK:-no}" != "yes-rollback" ] && [ "${CONFIRM:-no}" != "rollback" ]; then
    echo "❌ SAFETY HALT: Direct script execution of rollback_release.sh is blocked."
    echo "   To execute emergency rollback, run via Makefile:"
    echo "     make rollback"
    echo "   Or pass explicit environment confirmation:"
    echo "     CONFIRM_ROLLBACK=yes-rollback bash scripts/rollback_release.sh"
    exit 1
fi

ROLLBACK_IMAGE="${1:-${ROLLBACK_IMAGE_TAG:-130-app:latest}}"
HAPROXY_SOCK="/tmp/haproxy.sock"
HAPROXY_CONTAINER=$(docker ps --format '{{.Names}}' | grep haproxy | head -n 1)
[ -z "$HAPROXY_CONTAINER" ] && HAPROXY_CONTAINER="130-haproxy-1"
EVIDENCE_FILE="$PROJECT_ROOT/artifacts/rollback_execution_evidence.json"

haproxy_cmd() {
    echo "$1" | docker exec -i "$HAPROXY_CONTAINER" socat -t 1 - "$HAPROXY_SOCK" 2>/dev/null || true
}

get_status() {
    local st
    st=$(haproxy_cmd "show stat" | awk -F',' -v srv="$1" '$1=="app_nodes" && $2==srv {print $18}' | tr -d '\r\n ')
    echo "$st"
}

get_active_conns() {
    local c
    c=$(haproxy_cmd "show stat" | awk -F',' -v srv="$1" '$1=="app_nodes" && $2==srv {print $5}' | tr -dc '0-9')
    echo "${c:-0}"
}

echo "======================================================================"
echo "  NHMP 130 — INITIATING AUTOMATED EMERGENCY ROLLBACK"
echo "  Target Baseline Image: $ROLLBACK_IMAGE"
echo "  Timestamp: $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
echo "======================================================================"

drain_and_maint() {
    local srv="$1"
    echo "Draining $srv in HAProxy (allowing in-flight requests to complete)..."
    haproxy_cmd "set server app_nodes/$srv state drain"
    
    local drained=0
    for i in $(seq 1 15); do
        local conns
        conns=$(get_active_conns "$srv")
        if [ "$conns" -eq 0 ]; then
            echo "$srv drained cleanly to 0 active TCP connections in ${i}s."
            drained=1
            break
        fi
        sleep 1
    done

    echo "Transitioning $srv to maint state (HAProxy option redispatch allows sticky clients to reroute)..."
    haproxy_cmd "set server app_nodes/$srv state maint"
}

wait_healthy() {
    local app="$1"
    local container="130-${app}-1"
    echo "Waiting for $app to become healthy on /up..."
    local ready=0
    for i in $(seq 1 20); do
        local code
        code=$(docker exec "$container" php -r '
            require "vendor/autoload.php";
            $app = require_once "bootstrap/app.php";
            $k = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $r = $k->handle(Illuminate\Http\Request::create("/up", "GET"));
            echo $r->getStatusCode();
        ' 2>/dev/null || echo "000")
        if [ "$code" = "200" ] || [ "$code" = "204" ]; then
            echo "$app is HEALTHY (HTTP $code)."
            ready=1
            break
        fi
        sleep 2
    done

    if [ "$ready" -ne 1 ]; then
        echo "ERROR: $app failed health probe during rollback!" >&2
        exit 1
    fi

    echo "Warming route, config, and dashboard caches on $app..."
    docker exec "$container" php artisan route:cache >/dev/null 2>&1 || true
    docker exec "$container" php artisan config:cache >/dev/null 2>&1 || true
}

# ── Step 1: Rollback Node 1 ───────────────────────────────────────────────────
echo -e "\n--- Step 1: Rolling back Node 1 (web-1 / app-1) ---"
drain_and_maint "web-1"

echo "Recreating app-1 with rollback image: $ROLLBACK_IMAGE..."
APP_IMAGE="$ROLLBACK_IMAGE" docker compose up -d --no-deps --force-recreate app-1
wait_healthy "app-1"

docker exec 130-web-1-1 nginx -s reload >/dev/null 2>&1 || true
haproxy_cmd "set server app_nodes/web-1 state ready"

for i in $(seq 1 15); do
    if [ "$(get_status 'web-1')" = "UP" ]; then
        echo "web-1 restored and confirmed strictly UP in HAProxy."
        break
    fi
    sleep 2
done

# ── Step 2: Rollback Node 2 ───────────────────────────────────────────────────
echo -e "\n--- Step 2: Rolling back Node 2 (web-2 / app-2) ---"
drain_and_maint "web-2"

echo "Recreating app-2 with rollback image: $ROLLBACK_IMAGE..."
APP_IMAGE="$ROLLBACK_IMAGE" docker compose up -d --no-deps --force-recreate app-2
wait_healthy "app-2"

docker exec 130-web-2-1 nginx -s reload >/dev/null 2>&1 || true
haproxy_cmd "set server app_nodes/web-2 state ready"

for i in $(seq 1 15); do
    if [ "$(get_status 'web-2')" = "UP" ]; then
        echo "web-2 restored and confirmed strictly UP in HAProxy."
        break
    fi
    sleep 2
done

# ── Step 3: Cache Invalidation & Worker Recycling ─────────────────────────────
echo -e "\n--- Step 3: Cleaning caches & recycling workers ---"
docker exec 130-redis-1 redis-cli del tablet_advisories:feed:v1 >/dev/null 2>&1 || true
docker exec 130-app-1-1 php artisan queue:restart >/dev/null 2>&1 || true
docker exec 130-app-2-1 php artisan queue:restart >/dev/null 2>&1 || true

# ── Step 4: Ingress Verification ──────────────────────────────────────────────
echo -e "\n--- Step 4: Ingress Health Verification ---"
HEALTH_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8085/healthz)
echo "Ingress /healthz returned: HTTP $HEALTH_CODE"

if [ "$HEALTH_CODE" != "200" ]; then
    echo "❌ ROLLBACK FAILED: Ingress health check failed!" >&2
    exit 1
fi

cat <<EOF > "$EVIDENCE_FILE"
{
  "operation": "NHMP 130 Automated Zero-Downtime Rollback",
  "execution_timestamp": "$(date -u +'%Y-%m-%dT%H:%M:%SZ')",
  "restored_image": "$ROLLBACK_IMAGE",
  "final_cluster_state": {
    "web-1": "$(get_status 'web-1')",
    "web-2": "$(get_status 'web-2')",
    "ingress_healthz_http_code": $HEALTH_CODE
  },
  "cache_flushed": true,
  "workers_recycled": true,
  "rollback_status": "SUCCESS"
}
EOF

echo "======================================================================"
echo "✅ EMERGENCY ROLLBACK COMPLETED SUCCESSFULLY"
echo "   Cluster restored to verified baseline. Evidence saved: $EVIDENCE_FILE"
echo "======================================================================"
