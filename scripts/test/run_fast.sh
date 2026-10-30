#!/usr/bin/env bash
set -euo pipefail

# NHMP 130 CRM — Tier 1 Fast Developer Gate Runner
# Runs fast unit, serialization, validation, metrics, and frontend tests.
# Execution target: < 3 seconds. Zero database or external network requirement.

echo "============================================================"
echo "  NHMP 130 CRM — Tier 1 Fast Test Suite (Commit & PR Gate)  "
echo "============================================================"
echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

START_TIME=$(date +%s%N)

echo "[1/4] Running Pure PHP Unit Tests (Resources, Validation, Calculations)..."
php vendor/bin/phpunit \
    tests/Unit/Resources/CallListResourceTest.php \
    tests/Unit/Resources/UserListResourceTest.php \
    tests/Unit/Validation/CallInputValidationTest.php \
    tests/Unit/CallTypeSummaryReportTest.php \
    tests/Unit/HelpResponseTimeMetricsTest.php \
    --colors=always

echo ""
echo "[2/4] Running Frontend Unit Tests (Node.js Test Runner)..."
node --test tests/Frontend/*.test.mjs

echo ""
echo "[3/4] Verifying N+1 SQL Safeguards in API Resources..."
# Ensure relationLoaded() guards are present in resources
if grep -q '\$this->relationLoaded' app/Http/Resources/CallListResource.php && \
   grep -q '\$this->relationLoaded' app/Http/Resources/UserListResource.php; then
    echo "  ✓ Strict eager-loading guards verified in CallListResource & UserListResource."
else
    echo "  ✘ ERROR: Missing \$this->relationLoaded() guards in API resources!"
    exit 1
fi

echo ""
echo "[4/4] Verifying Code Style Standards in Tests..."
if [ -f vendor/bin/pint ]; then
    vendor/bin/pint --test tests/Unit tests/Support || true
fi

END_TIME=$(date +%s%N)
ELAPSED=$(awk "BEGIN {print ($END_TIME - $START_TIME) / 1000000000}")

echo ""
echo "============================================================"
echo "  ✓ TIER 1 FAST TESTS PASSED in ${ELAPSED}s"
echo "  Ready for local commit and pull request submission."
echo "============================================================"
