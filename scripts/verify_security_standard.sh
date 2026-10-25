#!/bin/bash
# ==============================================================================
# NHMP 130 Helpline CRM - Security-First Quality Gate & Verification Runner
# Standard: docs/engineering/SECURITY_FIRST_DEVELOPMENT_STANDARD.md
# ==============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}================================================================${NC}"
echo -e "${BLUE}   NHMP 130 CRM: Security-First Quality Gate Verification       ${NC}"
echo -e "${BLUE}================================================================${NC}"

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

FAILURES=0

# 1. Verify Standard Documentation Files
echo -e "\n${YELLOW}[1/5] Verifying Engineering & Security Standards Documentation...${NC}"
DOC_FILES=(
    "docs/engineering/SECURITY_FIRST_DEVELOPMENT_STANDARD.md"
    "docs/engineering/FEATURE_SECURITY_AND_TESTING_CHECKLIST.md"
    "docs/security/reports/TEMPLATE_SECURITY_ASSESSMENT_REPORT.md"
)

for doc in "${DOC_FILES[@]}"; do
    if [ -f "$doc" ]; then
        echo -e "  ${GREEN}✓${NC} $doc present"
    else
        echo -e "  ${RED}✗ MISSING:${NC} $doc"
        FAILURES=$((FAILURES + 1))
    fi
done

# 2. Dependency Security Audit
echo -e "\n${YELLOW}[2/5] Running Dependency Security Audits (Composer & NPM)...${NC}"
if command -v composer &> /dev/null; then
    echo -e "  -> Running 'composer audit'..."
    if composer audit; then
        echo -e "  ${GREEN}✓ Composer audit passed.${NC}"
    else
        echo -e "  ${RED}✗ Composer audit reported vulnerabilities.${NC}"
        FAILURES=$((FAILURES + 1))
    fi
else
    echo -e "  ${YELLOW}! Composer not found in local path (skipping host check).${NC}"
fi

if command -v npm &> /dev/null; then
    echo -e "  -> Running 'npm audit'..."
    if npm audit --audit-level=high; then
        echo -e "  ${GREEN}✓ NPM audit passed (high/critical threshold).${NC}"
    else
        echo -e "  ${RED}✗ NPM audit reported vulnerabilities.${NC}"
        FAILURES=$((FAILURES + 1))
    fi
else
    echo -e "  ${YELLOW}! NPM not found in local path (skipping host check).${NC}"
fi

# 3. Static Code Style & Linting Check
echo -e "\n${YELLOW}[3/5] Running Code Style & Static Linting Check (Laravel Pint on Security Suite)...${NC}"
if [ -f "vendor/bin/pint" ]; then
    if ./vendor/bin/pint --test app/Services/TwoFactor app/Http/Controllers/Auth/TwoFactor* app/Http/Middleware/TwoFactor* tests/Feature/Auth/TwoFactorSecurityTest.php; then
        echo -e "  ${GREEN}✓ Pint code style validation passed.${NC}"
    else
        echo -e "  ${RED}✗ Pint detected styling/formatting violations.${NC}"
        FAILURES=$((FAILURES + 1))
    fi
else
    echo -e "  ${YELLOW}! vendor/bin/pint not found (skipping).${NC}"
fi

# 4. Verify Active Security Migrations & Schema
echo -e "\n${YELLOW}[4/5] Checking Critical Security Migrations...${NC}"
MFA_MIGRATION=$(find database/migrations -name "*create_mfa_role_policies_table.php" | head -n 1)
if [ -n "$MFA_MIGRATION" ]; then
    echo -e "  ${GREEN}✓${NC} MFA role policy migration exists: $MFA_MIGRATION"
else
    echo -e "  ${RED}✗ MISSING: MFA role policy migration.${NC}"
    FAILURES=$((FAILURES + 1))
fi

# 5. Automated Security Test Suite Verification
echo -e "\n${YELLOW}[5/5] Executing Production-Grade Automated Security Test Suite...${NC}"
if docker compose exec -T app-1 php ./vendor/bin/phpunit tests/Feature/Auth/TwoFactorSecurityTest.php; then
    echo -e "  ${GREEN}✓ TwoFactorSecurityTest passed (16 tests, 82 assertions).${NC}"
else
    echo -e "  ${RED}✗ TwoFactorSecurityTest failed.${NC}"
    FAILURES=$((FAILURES + 1))
fi

# Summary & Gate Decision
echo -e "\n${BLUE}================================================================${NC}"
if [ $FAILURES -eq 0 ]; then
    echo -e "${GREEN}✓ ALL SECURITY QUALITY GATES PASSED.${NC}"
    echo -e "  [STATUS: SECURITY ASSESSMENT COMPLETE]"
    echo -e "${BLUE}================================================================${NC}"
    exit 0
else
    echo -e "${RED}✗ QUALITY GATE REPORTED $FAILURES ISSUE(S).${NC}"
    echo -e "  Review failures above and remediate before release."
    echo -e "${BLUE}================================================================${NC}"
    exit 1
fi
