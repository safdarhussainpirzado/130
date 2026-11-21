#!/usr/bin/env bash
# ==============================================================================
# NHMP 130 - Desk Push Notifications Rollout Preflight & Live Checklist
#
# READ-ONLY / PRINT-ONLY VERIFICATION SCRIPT.
# This script does NOT modify any configuration, database tables, or container state.
# Safe to execute at any time on staging or production.
# ==============================================================================
set -euo pipefail

BOLD="\033[1m"
GREEN="\033[0;32m"
YELLOW="\033[0;33m"
RED="\033[0;31m"
BLUE="\033[0;34m"
RESET="\033[0m"

echo -e "${BOLD}================================================================================${RESET}"
echo -e "${BOLD}NHMP 130 Desk Push Notifications - Rollout Verification Checklist${RESET}"
echo -e "Timestamp : $(date '+%Y-%m-%d %H:%M:%S %Z')"
echo -e "Hostname  : $(hostname)"
echo -e "Worktree  : $(pwd)"
echo -e "${BOLD}================================================================================${RESET}"

CHECK_FAILURES=0
CHECK_WARNINGS=0

pass() {
    echo -e "  [${GREEN}PASS${RESET}] $1"
}

warn() {
    echo -e "  [${YELLOW}WARN${RESET}] $1"
    CHECK_WARNINGS=$((CHECK_WARNINGS + 1))
}

fail() {
    echo -e "  [${RED}FAIL${RESET}] $1"
    CHECK_FAILURES=$((CHECK_FAILURES + 1))
}

info() {
    echo -e "  [${BLUE}INFO${RESET}] $1"
}

# ------------------------------------------------------------------------------
# 1. Composer Dependencies & Library Presence
# ------------------------------------------------------------------------------
echo ""
echo -e "${BOLD}1. PHP Libraries & Composer Lock Validation${RESET}"
if [ -f "composer.lock" ]; then
    if grep -q '"name": "minishlink/web-push"' composer.lock; then
        pass "composer.lock contains minishlink/web-push"
    else
        fail "minishlink/web-push is missing from composer.lock"
    fi

    if grep -q '"name": "google/auth"' composer.lock; then
        pass "composer.lock contains google/auth"
    else
        fail "google/auth is missing from composer.lock"
    fi
else
    warn "composer.lock not found in current directory"
fi

if [ -f "vendor/autoload.php" ]; then
    if php -r 'require "vendor/autoload.php"; exit(class_exists("Minishlink\\WebPush\\WebPush") ? 0 : 1);' >/dev/null 2>&1; then
        pass "Minishlink\\WebPush\\WebPush class is loadable in PHP runtime"
    else
        warn "Minishlink\\WebPush\\WebPush class could not be loaded via local vendor/autoload.php"
    fi

    if php -r 'require "vendor/autoload.php"; exit(class_exists("Google\\Auth\\Credentials\\ServiceAccountCredentials") ? 0 : 1);' >/dev/null 2>&1; then
        pass "Google\\Auth\\Credentials\\ServiceAccountCredentials class is loadable in PHP runtime"
    else
        warn "Google\\Auth\\Credentials\\ServiceAccountCredentials class could not be loaded via local vendor/autoload.php"
    fi
fi

# ------------------------------------------------------------------------------
# 2. File Permissions & Secret Storage Validation
# ------------------------------------------------------------------------------
echo ""
echo -e "${BOLD}2. Secrets Storage & File Permissions Check${RESET}"

if [ -d "secrets" ]; then
    SECRETS_PERM=$(stat -c "%a" secrets 2>/dev/null || stat -f "%Op" secrets 2>/dev/null || echo "unknown")
    info "Directory secrets/ permissions: ${SECRETS_PERM}"
else
    warn "Directory secrets/ does not exist locally (will be created in deployment container)"
fi

# VAPID Private Key
if [ -f "secrets/vapid_private_key" ]; then
    VAPID_PERM=$(stat -c "%a" secrets/vapid_private_key 2>/dev/null || echo "unknown")
    if [ "$VAPID_PERM" = "600" ] || [ "$VAPID_PERM" = "400" ]; then
        pass "secrets/vapid_private_key exists with secure permissions (${VAPID_PERM})"
    else
        warn "secrets/vapid_private_key has permissions ${VAPID_PERM} (expected 600 or 400)"
    fi
else
    info "secrets/vapid_private_key not present (expected on initial deploy before 'push:generate-vapid')"
fi

# Firebase Credentials
if [ -f "secrets/firebase_credentials.json" ]; then
    FCM_PERM=$(stat -c "%a" secrets/firebase_credentials.json 2>/dev/null || echo "unknown")
    if [ "$FCM_PERM" = "600" ] || [ "$FCM_PERM" = "400" ]; then
        pass "secrets/firebase_credentials.json exists with secure permissions (${FCM_PERM})"
    else
        warn "secrets/firebase_credentials.json has permissions ${FCM_PERM} (expected 600 or 400)"
    fi
else
    info "secrets/firebase_credentials.json not present (optional placeholder until Safdar provisions FCM)"
fi

# ------------------------------------------------------------------------------
# 3. Environment Configuration Flags
# ------------------------------------------------------------------------------
echo ""
echo -e "${BOLD}3. Environment Flags (.env Inspection)${RESET}"

if [ -f ".env" ]; then
    PUSH_ENABLED_VAL=$(grep -E '^PUSH_ENABLED=' .env | cut -d '=' -f2- | tr -d ' "' || echo "unset")
    VAPID_PUB_SET=$(grep -E '^VAPID_PUBLIC_KEY=' .env | cut -d '=' -f2- | tr -d ' "' || echo "")

    info "PUSH_ENABLED setting in .env: ${PUSH_ENABLED_VAL}"
    if [ "$PUSH_ENABLED_VAL" = "true" ]; then
        info "Push notifications are ENABLED in .env (Active Mode)"
    elif [ "$PUSH_ENABLED_VAL" = "false" ]; then
        pass "PUSH_ENABLED=false (Safe Dark Launch mode)"
    else
        warn "PUSH_ENABLED is not explicitly set in .env (defaults to false)"
    fi

    if [ -n "$VAPID_PUB_SET" ]; then
        pass "VAPID_PUBLIC_KEY is configured in .env (${#VAPID_PUB_SET} chars)"
    else
        warn "VAPID_PUBLIC_KEY is empty or missing in .env"
    fi
else
    warn ".env file not found in current directory"
fi

# ------------------------------------------------------------------------------
# 4. Service Worker Asset & Nginx Route Configuration
# ------------------------------------------------------------------------------
echo ""
echo -e "${BOLD}4. Service Worker & Web Server Configuration${RESET}"

if [ -f "public/sw.js" ]; then
    pass "public/sw.js service worker asset exists"
    SW_SIZE=$(wc -c < public/sw.js | tr -d ' ')
    info "public/sw.js size: ${SW_SIZE} bytes"
else
    fail "public/sw.js is missing"
fi

NGINX_CONF="docker/nginx/default.conf"
if [ -f "$NGINX_CONF" ]; then
    if grep -q 'location = /sw.js' "$NGINX_CONF"; then
        pass "$NGINX_CONF contains dedicated 'location = /sw.js' routing block"
        if grep -A 5 'location = /sw.js' "$NGINX_CONF" | grep -q 'Cache-Control.*no-cache'; then
            pass "$NGINX_CONF enforces 'Cache-Control: no-cache, no-store, must-revalidate' on /sw.js"
        else
            warn "Cache-Control no-cache directive missing in /sw.js block of $NGINX_CONF"
        fi
    else
        warn "$NGINX_CONF does not contain an explicit 'location = /sw.js' block"
    fi
else
    info "$NGINX_CONF not found locally (verify on production host /etc/nginx/sites-enabled/)"
fi

# ------------------------------------------------------------------------------
# 5. Runtime Container & Healthcheck Inspection
# ------------------------------------------------------------------------------
echo ""
echo -e "${BOLD}5. Docker Container Health & Worker Status${RESET}"

if command -v docker >/dev/null 2>&1; then
    PUSH_WORKER_CONTAINER=$(docker ps --filter "name=push-worker" --filter "status=running" --format "{{.Names}}" 2>/dev/null || true)
    if [ -n "$PUSH_WORKER_CONTAINER" ]; then
        pass "Push worker container '${PUSH_WORKER_CONTAINER}' is currently RUNNING"
    else
        info "Push worker container 'push-worker' is not running in current local docker engine"
    fi

    OCTANE_CONTAINER=$(docker ps --filter "name=app-octane" --filter "status=running" --format "{{.Names}}" 2>/dev/null || true)
    if [ -n "$OCTANE_CONTAINER" ]; then
        pass "Octane application container '${OCTANE_CONTAINER}' is currently RUNNING"
    else
        info "Octane application container is not running locally"
    fi
else
    info "docker CLI not available in current execution context"
fi

# ------------------------------------------------------------------------------
# Checklist Summary
# ------------------------------------------------------------------------------
echo ""
echo -e "${BOLD}================================================================================${RESET}"
echo -e "${BOLD}Checklist Summary${RESET}"
echo -e "Failures : ${CHECK_FAILURES}"
echo -e "Warnings : ${CHECK_WARNINGS}"
if [ $CHECK_FAILURES -eq 0 ]; then
    echo -e "Status   : ${GREEN}READY FOR ROLLOUT EVALUATION${RESET}"
    exit 0
else
    echo -e "Status   : ${RED}ATTENTION REQUIRED BEFORE PROCEEDING${RESET}"
    exit 1
fi
