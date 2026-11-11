#!/bin/bash
set -euo pipefail

# ==============================================================================
# NHMP 130 CRM — Guarded Deployment Wrapper Script
# ==============================================================================
# Mirrors Makefile deployment guards and independently enforces:
#   1. Environment parameter check (staging or production only).
#   2. Explicit confirmation token check (fails closed if missing).
#   3. Immutable image digest requirement (rejects mutable 'latest').
#   4. Strict remote connection isolation during local verification.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

ENV_NAME="${ENV:-${1:-}}"
CONFIRM_TOKEN="${CONFIRM:-${2:-}}"
IMAGE_DIGEST_VAL="${IMAGE_DIGEST:-${3:-}}"

if [ -z "$ENV_NAME" ]; then
    echo "❌ SAFETY HALT: Direct execution of deploy.sh requires explicit ENV."
    echo "   Usage: ENV=staging CONFIRM=deploy-staging bash scripts/deploy.sh"
    echo "      or: ENV=production CONFIRM=deploy-production IMAGE_DIGEST=sha256:... bash scripts/deploy.sh"
    exit 1
fi

if [ "$ENV_NAME" = "local" ] || [ "$ENV_NAME" = "test" ]; then
    echo "❌ SAFETY HALT: deploy.sh is for staging/production deployments only (current: $ENV_NAME)."
    exit 1
fi

if [ "$ENV_NAME" = "staging" ]; then
    if [ "$CONFIRM_TOKEN" != "deploy-staging" ] && [ "$CONFIRM_TOKEN" != "yes-deploy-nhmp-staging" ]; then
        echo "❌ CONFIRMATION REQUIRED: Staging deployment requires CONFIRM=deploy-staging"
        exit 1
    fi
    echo "==> Triggering Guarded Staging Deployment via Makefile..."
    make deploy-staging ENV=staging CONFIRM=deploy-staging
    exit $?
fi

if [ "$ENV_NAME" = "production" ]; then
    if [ "$CONFIRM_TOKEN" != "deploy-production" ] && [ "$CONFIRM_TOKEN" != "yes-deploy-nhmp-production" ]; then
        echo "❌ CRITICAL SAFETY GUARD: Production deployment requires CONFIRM=deploy-production"
        exit 1
    fi
    if [ -z "$IMAGE_DIGEST_VAL" ] || [ "$IMAGE_DIGEST_VAL" = "latest" ] || [ "$IMAGE_DIGEST_VAL" = "130-app:latest" ]; then
        echo "❌ SECURITY POLICY VIOLATION: Production deployment requires immutable IMAGE_DIGEST."
        exit 1
    fi
    echo "==> Triggering Guarded Production Deployment via Makefile..."
    make deploy-production ENV=production CONFIRM=deploy-production IMAGE_DIGEST="$IMAGE_DIGEST_VAL"
    exit $?
fi

echo "❌ INVALID ENVIRONMENT: Unknown environment '$ENV_NAME'."
exit 1
