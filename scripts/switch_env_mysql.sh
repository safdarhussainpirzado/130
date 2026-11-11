#!/bin/bash
set -euo pipefail

# ==============================================================================
# NHMP 130 CRM — Environment-Aware MySQL Configuration Switcher
# ==============================================================================
# Usage: ./scripts/switch_env_mysql.sh [local|staging|production] [--confirm-env-switch]
# Guarded against accidental workstation memory exhaustion and unintended restarts.
# ==============================================================================

TARGET_ENV="${1:-local}"
CONFIRM_FLAG="${2:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
MYSQL_DIR="$PROJECT_ROOT/mysql"

echo "======================================================================"
echo "  NHMP 130 CRM — MySQL Environment Configuration Switcher"
echo "======================================================================"

if [ "$TARGET_ENV" = "production" ] || [ "$TARGET_ENV" = "prod" ] || [ "$TARGET_ENV" = "staging" ] || [ "$TARGET_ENV" = "candidate" ]; then
    if [ "$CONFIRM_FLAG" != "--confirm-env-switch" ]; then
        echo "⚠️  SAFETY GUARD: Switching MySQL to '$TARGET_ENV' alters InnoDB memory allocations." >&2
        echo "   Local workstations may exhaust host RAM (staging=2GB, production=6GB buffer pool)." >&2
        echo "   To proceed, provide explicit authorization flag:" >&2
        echo "   $0 $TARGET_ENV --confirm-env-switch" >&2
        exit 1
    fi
fi

# Create safety backup of existing my.cnf if present
if [ -f "$MYSQL_DIR/my.cnf" ]; then
    cp -f "$MYSQL_DIR/my.cnf" "$MYSQL_DIR/my.cnf.bak"
    echo "📦 Backed up current active config to: mysql/my.cnf.bak"
fi

case "$TARGET_ENV" in
    local|dev)
        cp -f "$MYSQL_DIR/my.local.cnf" "$MYSQL_DIR/my.cnf"
        echo "✅ Active config switched to: mysql/my.local.cnf"
        echo "   Profile: Ultra-Low Memory (Buffer Pool: 128MB, RSS: ~140MB, 50 connections)"
        ;;
    staging|candidate)
        cp -f "$MYSQL_DIR/my.staging.cnf" "$MYSQL_DIR/my.cnf"
        echo "✅ Active config switched to: mysql/my.staging.cnf"
        echo "   Profile: Staging / Candidate (Buffer Pool: 2GB, RSS: ~2.5GB, 150 connections)"
        ;;
    production|prod)
        cp -f "$MYSQL_DIR/my.production.cnf" "$MYSQL_DIR/my.cnf"
        echo "✅ Active config switched to: mysql/my.production.cnf"
        echo "   Profile: Production ACID Durability (Buffer Pool: 6GB, RSS: ~7.5GB, 400 connections, 4 pool instances)"
        ;;
    *)
        echo "❌ Unknown environment: $TARGET_ENV. Allowed values: local, staging, production" >&2
        exit 1
        ;;
esac

echo ""
echo "ℹ️  SAFETY NOTICE: Container state is preserved. Configuration takes effect upon"
echo "   controlled sequential restart of database nodes (never all simultaneously)."
echo "======================================================================"
