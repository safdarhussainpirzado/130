#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

HAPROXY_SOCK="/tmp/haproxy.sock"
CURRENT_DRAINING_SERVER=""
CURRENT_APP_NODE=""
DEPLOYMENT_FAILED=0

haproxy_cmd() {
    docker compose exec -T haproxy socat stdio "$HAPROXY_SOCK" <<< "$1"
}

get_status() {
    haproxy_cmd "show stat" | awk -F',' -v srv="$1" '$1=="app_nodes" && $2==srv {print $18}'
}

get_active_conns() {
    haproxy_cmd "show stat" | awk -F',' -v srv="$1" '$1=="app_nodes" && $2==srv {print $5}'
}

# Trap handler for unexpected failures
failure_recovery_handler() {
    local exit_code=$?
    if [ "$exit_code" -ne 0 ] || [ "$DEPLOYMENT_FAILED" -ne 0 ]; then
        echo "" >&2
        echo "=================================================================" >&2
        echo "🚨 DEPLOYMENT FAILURE DETECTED (Exit code: $exit_code) 🚨" >&2
        echo "=================================================================" >&2
        if [ -n "$CURRENT_DRAINING_SERVER" ]; then
            echo "State Alert: Backend node '$CURRENT_DRAINING_SERVER' was in transition!" >&2
            echo "Current HAProxy state for $CURRENT_DRAINING_SERVER: $(get_status "$CURRENT_DRAINING_SERVER" 2>/dev/null || echo "UNKNOWN")" >&2
            echo "" >&2
            echo "SAFE OPERATIONAL RECOVERY PROCEDURE:" >&2
            echo "1. DO NOT automatically restore '$CURRENT_DRAINING_SERVER' to 'ready' if '$CURRENT_APP_NODE' is failing." >&2
            echo "   Restoring a failing backend will cause HTTP 502 Bad Gateway errors for clients." >&2
            echo "2. Inspect container logs to identify the root cause:" >&2
            echo "     docker compose logs --tail=100 $CURRENT_APP_NODE" >&2
            echo "3. To rollback $CURRENT_APP_NODE or fix its configuration:" >&2
            echo "     docker compose up -d --no-deps --force-recreate $CURRENT_APP_NODE" >&2
            echo "4. Once $CURRENT_APP_NODE responds with HTTP 200/204 to /up:" >&2
            echo "     docker compose exec -T haproxy socat stdio /tmp/haproxy.sock <<< 'set server app_nodes/$CURRENT_DRAINING_SERVER state ready'" >&2
            echo "5. Confirm cluster health in HAProxy stats:" >&2
            echo "     docker compose exec -T haproxy socat stdio /tmp/haproxy.sock <<< 'show stat' | grep app_nodes" >&2
            echo "=================================================================" >&2
        fi
    fi
}
trap failure_recovery_handler EXIT

drain_server() {
    local srv="$1"
    CURRENT_DRAINING_SERVER="$srv"
    echo "Initiating graceful drain of $srv in HAProxy..."
    haproxy_cmd "set server app_nodes/$srv state drain"
    
    local drained=0
    local final_conns="unknown"
    for i in $(seq 1 30); do
        local conns
        conns=$(get_active_conns "$srv")
        if ! [[ "$conns" =~ ^[0-9]+$ ]]; then
            echo "ERROR: Invalid non-numeric connection count ('$conns') for $srv! Reverting $srv to ready." >&2
            haproxy_cmd "set server app_nodes/$srv state ready"
            CURRENT_DRAINING_SERVER=""
            DEPLOYMENT_FAILED=1
            exit 1
        fi
        final_conns="$conns"
        if [ "$conns" -eq 0 ]; then
            echo "$srv drained cleanly to 0 active connections in ${i}s."
            drained=1
            break
        fi
        echo "Waiting for active connections to finish ($conns remaining, ${i}/30s)..."
        sleep 1
    done

    # Explicit assertion: active connections MUST be strictly 0 before recreating
    if [ "$drained" -ne 1 ] || [ "$final_conns" -ne 0 ]; then
        echo "ERROR: Drain failed! Active connections did not reach 0 within 30s ($final_conns remaining)." >&2
        echo "Aborting deployment before recreating node. Restoring $srv to ready state." >&2
        haproxy_cmd "set server app_nodes/$srv state ready"
        CURRENT_DRAINING_SERVER=""
        DEPLOYMENT_FAILED=1
        exit 1
    fi
    echo "CONFIRMED: $srv has 0 active connections. Safe to proceed with node recreation."
}

wait_healthy() {
    local app="$1"
    echo "Waiting for $app to become ready on /up..."
    local ready=0
    for i in $(seq 1 20); do
        local code
        code=$(docker compose exec -T "$app" php -r '
            require "vendor/autoload.php";
            $app = require_once "bootstrap/app.php";
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $resp = $kernel->handle($req = Illuminate\Http\Request::create("/up", "GET"));
            echo $resp->getStatusCode();
        ' 2>/dev/null || echo "000")
        if [ "$code" = "204" ] || [ "$code" = "200" ]; then
            echo "$app is HEALTHY (internal /up returned HTTP $code)."
            ready=1
            break
        fi
        sleep 2
    done

    if [ "$ready" -ne 1 ]; then
        echo "ERROR: $app failed health readiness probe within 40s (last code: $code)!" >&2
        DEPLOYMENT_FAILED=1
        exit 1
    fi
}

echo "=== STARTING CONTROLLED ROLLING DEPLOYMENT ==="

# Step 0: Pre-checks - BOTH nodes must be UP
STATUS_W1=$(get_status "web-1")
STATUS_W2=$(get_status "web-2")
echo "Initial Cluster Status: web-1 = $STATUS_W1, web-2 = $STATUS_W2"

if [ "$STATUS_W1" != "UP" ]; then
    echo "ABORT: web-1 is not in UP state (currently: '$STATUS_W1'). Cluster is not fully healthy!" >&2
    exit 1
fi

if [ "$STATUS_W2" != "UP" ]; then
    echo "ABORT: web-2 is not in UP state (currently: '$STATUS_W2'). Cluster is not fully healthy!" >&2
    exit 1
fi

# Step 0.5: Explicit Release Asset Publication across Mount Boundary
echo "--- Step 0.5: Publishing new release assets across mount boundary to host ./public ---"
bash "$SCRIPT_DIR/publish_release_assets.sh" "130-app-1:latest"

# ── PHASE 1: Deploy Node 1 ────────────────────────────────────────────────────
echo "--- Phase 1: Upgrading Node 1 (web-1 / app-1) ---"
CURRENT_APP_NODE="app-1"
drain_server "web-1"

echo "Recreating app-1..."
docker compose up -d --no-deps --force-recreate app-1
wait_healthy "app-1"

echo "Restoring web-1 to ready state in HAProxy..."
haproxy_cmd "set server app_nodes/web-1 state ready"

# Verify web-1 returns to UP
for i in $(seq 1 15); do
    STATUS_W1=$(get_status "web-1")
    if [ "$STATUS_W1" = "UP" ]; then
        echo "web-1 restored and confirmed UP in HAProxy."
        break
    fi
    sleep 2
done

STATUS_W1=$(get_status "web-1")
if [ "$STATUS_W1" != "UP" ]; then
    echo "ABORT: web-1 did not return to UP state (currently: '$STATUS_W1'). Cannot proceed to web-2!" >&2
    DEPLOYMENT_FAILED=1
    exit 1
fi
CURRENT_DRAINING_SERVER=""
CURRENT_APP_NODE=""

# ── PHASE 2: Deploy Node 2 ────────────────────────────────────────────────────
echo "--- Phase 2: Upgrading Node 2 (web-2 / app-2) ---"
CURRENT_APP_NODE="app-2"
drain_server "web-2"

echo "Recreating app-2..."
docker compose up -d --no-deps --force-recreate app-2
wait_healthy "app-2"

echo "Restoring web-2 to ready state in HAProxy..."
haproxy_cmd "set server app_nodes/web-2 state ready"

# Verify web-2 returns to UP
for i in $(seq 1 15); do
    STATUS_W2=$(get_status "web-2")
    if [ "$STATUS_W2" = "UP" ]; then
        echo "web-2 restored and confirmed UP in HAProxy."
        break
    fi
    sleep 2
done

STATUS_W2=$(get_status "web-2")
if [ "$STATUS_W2" != "UP" ]; then
    echo "ABORT: web-2 did not return to UP state (currently: '$STATUS_W2')." >&2
    DEPLOYMENT_FAILED=1
    exit 1
fi
CURRENT_DRAINING_SERVER=""
CURRENT_APP_NODE=""

echo "=== ROLLING DEPLOYMENT COMPLETED SUCCESSFULLY ==="
echo "Final Cluster State: web-1 = $(get_status 'web-1'), web-2 = $(get_status 'web-2')"
