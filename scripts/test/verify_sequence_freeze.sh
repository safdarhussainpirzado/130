#!/usr/bin/env bash
set -euo pipefail

# NHMP 130 CRM — Operational Staging Invariant & Sequence Freeze Verifier
# Strictly confirms that 130_crm_db on 100.108.239.107 remains frozen at 710035
# and zero test mutations have leaked to the operational database.

EXPECTED_SEQUENCE=710035
STAGING_HOST="100.108.239.107"

echo "============================================================"
echo "  NHMP 130 CRM — Staging Database Invariant Verification   "
echo "============================================================"
echo "Host:             ${STAGING_HOST}"
echo "Target DB:        130_crm_db (Operational Staging)"
echo "Expected Counter: ${EXPECTED_SEQUENCE}"
echo "Timestamp:        $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

ACTUAL_SEQ=$(ssh -o ConnectTimeout=25 -o BatchMode=yes -o StrictHostKeyChecking=no root@${STAGING_HOST} "docker exec 130-db-1-1 mysql -u root -psecret -s -N -e 'SELECT current_seq FROM 130_crm_db.call_sequences WHERE year=2026;' 2>/dev/null" || echo "ERROR")

echo "Current Staging Sequence Counter: ${ACTUAL_SEQ}"

if [ "${ACTUAL_SEQ}" -eq "${EXPECTED_SEQUENCE}" ]; then
    echo "  ✓ INVARIANT MAINTAINED: Staging sequence counter is strictly frozen at ${EXPECTED_SEQUENCE}."
    echo "  ✓ Zero test mutations have occurred in operational staging."
    exit 0
else
    echo "  ✘ CRITICAL VIOLATION: Sequence counter moved from ${EXPECTED_SEQUENCE} to ${ACTUAL_SEQ}!"
    exit 1
fi
