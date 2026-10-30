#!/usr/bin/env bash
set -euo pipefail

# NHMP 130 CRM — Master Release Candidate Acceptance Pipeline
# Enforces Rule 3: Every release must run the reusable automated test suites before approval.

echo "=================================================================="
echo "    NHMP 130 CRM — OFFICIAL RELEASE CANDIDATE ACCEPTANCE PIPELINE "
echo "=================================================================="
echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Host:      $(hostname)"
echo ""

TOTAL_START=$(date +%s)

# Stage 1: Tier 1 Fast Developer Gate
echo ">>> [STAGE 1/4] Running Tier 1 Fast Unit & Serialization Gate..."
bash scripts/test/run_fast.sh

# Stage 2: Security & Penetration Audit
echo ""
echo ">>> [STAGE 2/4] Running Security, Secret & MFA Penetration Audit..."
bash scripts/test/run_security.sh

# Stage 3: Operational Staging Database Invariant & Sequence Freeze Check
echo ""
echo ">>> [STAGE 3/4] Verifying Operational Staging Invariant (Freeze at 710035)..."
bash scripts/test/verify_sequence_freeze.sh

# Stage 4: Tier 2 Playwright End-to-End Browser Automation
echo ""
echo ">>> [STAGE 4/4] Running Tier 2 Playwright Browser Automation Suites..."
bash scripts/test/run_browser.sh

TOTAL_END=$(date +%s)
TOTAL_TIME=$((TOTAL_END - TOTAL_START))

echo ""
echo "=================================================================="
echo "  🎉 ALL ACCEPTANCE GATES PASSED IN ${TOTAL_TIME}s"
echo "  Technical QA:  PASSED (Unit, API Serializers, N+1 Guards)"
echo "  Security QA:   PASSED (OWASP Top 10, MFA Immunity, Secret Shielding)"
echo "  Operational:   PASSED (Live Browser Flows, Sequence Frozen at 710035)"
echo "  Release is APPROVED for Production Rollout."
echo "=================================================================="
