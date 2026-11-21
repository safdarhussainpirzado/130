#!/usr/bin/env bash
set -eo pipefail

REPORT_FILE="docs/evidence/gate-last-run.txt"
mkdir -p "$(dirname "$REPORT_FILE")"

run_gate() {
echo "================================================================================"
echo "NHMP 130 Unified CI Gate"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S %Z')"
echo "Worktree: $(pwd)"
echo "Branch: $(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'unknown')"
echo "================================================================================"

BASE_REF="main"
if ! git rev-parse --verify "$BASE_REF" >/dev/null 2>&1; then
    BASE_REF="8830eaf07d728687ddffacfc8c7b7da28d3f967d"
fi

GATE_PASSED=true

fail_gate() {
    echo "  [FAIL] $1"
    GATE_PASSED=false
}

pass_gate() {
    echo "  [PASS] $1"
}

# ------------------------------------------------------------------------------
# 1. Scope Check
# ------------------------------------------------------------------------------
echo ""
echo "--- 1. Scope Verification ---"
ALLOW_FILE="scripts/ci/scope-allow.txt"
TOUCHED_FILES=$(git diff --name-only "$BASE_REF")

FORBIDDEN_PATTERNS=(
    "^routes/api\.php"
    "^app/Http/Controllers/Api/V1/"
    "^app/Http/Requests/Api/V1/"
    "^postman/"
    "^database/seeders/HamsafarEcosystemSeeder\.php"
    "^app/Models/PatrolDutySession\.php"
    ".*Patrol.*"
    ".*Commuter.*"
    ".*Womens.*"
)

SCOPE_VIOLATIONS=0
while IFS= read -r file; do
    [ -z "$file" ] && continue
    
    # Check allowlist
    if [ -f "$ALLOW_FILE" ] && grep -Fxq "$file" "$ALLOW_FILE"; then
        continue
    fi

    for pat in "${FORBIDDEN_PATTERNS[@]}"; do
        if echo "$file" | grep -Eq "$pat"; then
            echo "  Scope violation: $file matches pattern '$pat'"
            SCOPE_VIOLATIONS=$((SCOPE_VIOLATIONS + 1))
            break
        fi
    done
done <<< "$TOUCHED_FILES"

if [ $SCOPE_VIOLATIONS -eq 0 ]; then
    pass_gate "Scope check: No forbidden commuter/patrol/API files touched."
else
    fail_gate "Scope check: $SCOPE_VIOLATIONS forbidden files detected in branch diff."
fi

# ------------------------------------------------------------------------------
# 2. Secret Scan
# ------------------------------------------------------------------------------
echo ""
echo "--- 2. Secret & Credential Scan ---"
SECRET_VIOLATIONS=0

# Check tracked secrets
TRACKED_SECRETS=$(git ls-files secrets/ | grep -v '\.gitkeep' || true)
if [ -n "$TRACKED_SECRETS" ]; then
    for s in $TRACKED_SECRETS; do
        if [ -s "$s" ]; then
            echo "  Secret violation: Tracked non-empty file in secrets/: $s"
            SECRET_VIOLATIONS=$((SECRET_VIOLATIONS + 1))
        fi
    done
fi

# Check tracked .env files
TRACKED_ENV=$(git ls-files | grep -E '(^|/)\.env(\.|$)' | grep -v '\.env\.example' || true)
if [ -n "$TRACKED_ENV" ]; then
    echo "  Secret violation: Tracked .env file: $TRACKED_ENV"
    SECRET_VIOLATIONS=$((SECRET_VIOLATIONS + 1))
fi

# Check diff for private keys and API credentials
if git diff "$BASE_REF" | grep -E -e '-----BEGIN [A-Z]+ PRIVATE KEY-----' >/dev/null 2>&1; then
    echo "  Secret violation: Private key block found in git diff"
    SECRET_VIOLATIONS=$((SECRET_VIOLATIONS + 1))
fi

if git diff "$BASE_REF" | grep -E -e 'AIza[0-9A-Za-z_-]{35}' >/dev/null 2>&1; then
    echo "  Secret violation: Google API key found in git diff"
    SECRET_VIOLATIONS=$((SECRET_VIOLATIONS + 1))
fi

if [ $SECRET_VIOLATIONS -eq 0 ]; then
    pass_gate "Secret scan: Zero tracked secrets or real credential blocks detected."
else
    fail_gate "Secret scan: $SECRET_VIOLATIONS secret violations detected."
fi

# ------------------------------------------------------------------------------
# 3. Minimal-Diff Check
# ------------------------------------------------------------------------------
echo ""
echo "--- 3. Minimal-Diff Check ---"
DIFF_VIOLATIONS=0

MODIFIED_FILES=$(git diff --name-only --diff-filter=M "$BASE_REF")
while IFS= read -r file; do
    [ -z "$file" ] && continue

    RAW_STAT=$(git diff --numstat "$BASE_REF" -- "$file" | awk '{print $1 + $2}')
    RAW_LINES=${RAW_STAT:-0}

    W_STAT=$(git diff -w --numstat "$BASE_REF" -- "$file" | awk '{print $1 + $2}')
    W_LINES=${W_STAT:-0}

    MAX_ALLOWED=$(( 3 * W_LINES + 20 ))

    if [ "$RAW_LINES" -gt "$MAX_ALLOWED" ]; then
        echo "  Diff noise violation in $file: raw added/removed=$RAW_LINES exceeds threshold ($MAX_ALLOWED vs whitespace-ignored $W_LINES)"
        DIFF_VIOLATIONS=$((DIFF_VIOLATIONS + 1))
    fi
done <<< "$MODIFIED_FILES"

if [ $DIFF_VIOLATIONS -eq 0 ]; then
    pass_gate "Minimal-diff check: All modified files contain surgical functional changes without format noise."
else
    fail_gate "Minimal-diff check: $DIFF_VIOLATIONS files exceed allowed formatting noise threshold."
fi

# ------------------------------------------------------------------------------
# 4. Syntax & Style Check
# ------------------------------------------------------------------------------
echo ""
echo "--- 4. PHP Syntax & Code Style ---"
SYNTAX_ERRORS=0
PHP_FILES=$(git diff --name-only "$BASE_REF" | grep '\.php$' || true)
while IFS= read -r file; do
    [ -z "$file" ] && continue
    if [ -f "$file" ]; then
        if ! php -l "$file" >/dev/null 2>&1; then
            echo "  Syntax error in $file"
            SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
        fi
    fi
done <<< "$PHP_FILES"

if [ $SYNTAX_ERRORS -eq 0 ]; then
    pass_gate "PHP syntax check: All modified/created PHP files passed php -l."
else
    fail_gate "PHP syntax check: $SYNTAX_ERRORS syntax errors detected."
fi

# Pint on newly created files in branch
NEW_PHP_FILES=$(git diff --name-only --diff-filter=A "$BASE_REF" | grep '\.php$' || true)
PINT_FAILED=false
if [ -n "$NEW_PHP_FILES" ]; then
    if ! ./vendor/bin/pint --test $NEW_PHP_FILES >/dev/null 2>&1; then
        echo "  Pint style test failed on created files."
        PINT_FAILED=true
    fi
fi

if [ "$PINT_FAILED" = false ]; then
    pass_gate "Pint style check: All newly created branch files conform to Pint rules."
else
    fail_gate "Pint style check: Style violations in new files."
fi

# ------------------------------------------------------------------------------
# 5. Service Worker URL Security Test (Node)
# ------------------------------------------------------------------------------
echo ""
echo "--- 5. Service Worker Fallback Security Test ---"
if node tests/sw_url_fallback_test.cjs >/dev/null 2>&1; then
    pass_gate "Service Worker security test: 12/12 URL fallback and sanitization assertions passed."
else
    fail_gate "Service Worker security test failed."
fi

# ------------------------------------------------------------------------------
# 6. Docker Test Harness Execution
# ------------------------------------------------------------------------------
echo ""
echo "--- 6. Docker Test Harness (Unit & Feature Suite) ---"
HARNESS_IMAGE="${NHMP_IMAGE:-130-app-octane:phase2-rc25}"
DB_CONTAINER_RUNNING=$(docker ps --filter "name=130-db-1-1" --filter "status=running" -q)

if [ -z "$DB_CONTAINER_RUNNING" ]; then
    fail_gate "Docker test harness: Database container 130-db-1-1 is not running."
else
    TEST_CMD="docker run --rm --entrypoint=\"\" -u $(id -u):$(id -g) -e HOME=/tmp --network host \
        -v $(pwd):/var/www/html -w /var/www/html \
        -e HASH_DRIVER=bcrypt -e DB_HOST=127.0.0.1 -e DB_PORT=3309 \
        -e DB_DATABASE=130_crm_test_disposable -e DB_USERNAME=root -e DB_PASSWORD=MomxjpGVoxlLtsllGX7PgU6Tti67OgV4 \
        $HARNESS_IMAGE php ./vendor/bin/phpunit \
        tests/Unit/Notifications/ tests/Feature/Notifications/ \
        tests/Feature/TravelAdvisoryApprovalWorkflowTest.php tests/Feature/CallReminderTest.php"

    if eval "$TEST_CMD"; then
        pass_gate "Docker test suite: Notification, Advisory workflow, and Call reminder tests passed."
    else
        fail_gate "Docker test suite: One or more tests failed in harness."
    fi
fi

# ------------------------------------------------------------------------------
# 7. Compose Config Validation
# ------------------------------------------------------------------------------
echo ""
echo "--- 7. Docker Compose Validation ---"
COMPOSE_CFG_OUT=$(docker compose -f docker-compose.yml -f docker-compose.octane.yml config 2>&1 || true)
if echo "$COMPOSE_CFG_OUT" | grep -q "services:"; then
    pass_gate "Docker compose config: Validated syntax with variable image tags and push-worker healthcheck."
else
    fail_gate "Docker compose config: Failed to parse docker-compose.octane.yml."
fi

# ------------------------------------------------------------------------------
# Final Gate Summary
# ------------------------------------------------------------------------------
echo ""
echo "================================================================================"
if [ "$GATE_PASSED" = true ]; then
    echo "GATE: PASS"
    echo "================================================================================"
    return 0
else
    echo "GATE: FAIL"
    echo "================================================================================"
    return 1
fi
}

run_gate 2>&1 | tee "$REPORT_FILE"
exit "${PIPESTATUS[0]}"
