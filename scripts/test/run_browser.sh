#!/usr/bin/env bash
set -euo pipefail

# NHMP 130 CRM — Tier 2 Playwright Browser Test Suite Runner
# Runs end-to-end browser automation against Candidate Edge or target URL.

TARGET_URL="${CANDIDATE_URL:-http://100.108.239.107:8080}"

echo "============================================================"
echo "  NHMP 130 CRM — Tier 2 Browser Automation (Release Gate)   "
echo "============================================================"
echo "Target URL:  ${TARGET_URL}"
echo "Timestamp:   $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Ensure Target is Reachable
echo "[1/5] Verifying Target Connectivity..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "${TARGET_URL}/login" || true)
if [ "$HTTP_STATUS" != "200" ]; then
    echo "  ✘ ERROR: Cannot reach ${TARGET_URL}/login (HTTP Status: $HTTP_STATUS)"
    echo "  Ensure the candidate edge stack is running on 100.108.239.107."
    exit 1
fi
echo "  ✓ Target is online and responding with HTTP 200."

echo ""
echo "[2/2] Executing Full Playwright End-to-End Suite (Auth, Dashboard, Calls, Reports)..."
npx playwright test \
    tests/Browser/auth.spec.ts \
    tests/Browser/dashboard.spec.ts \
    tests/Browser/calls.spec.ts \
    tests/Browser/reports.spec.ts \
    tests/Browser/multi_role_workflow.spec.ts

echo ""
echo "============================================================"
echo "  ✓ ALL BROWSER AUTOMATION SUITES PASSED SUCCESSFULLY       "
echo "  HTML Report available at: playwright-report/index.html    "
echo "============================================================"
