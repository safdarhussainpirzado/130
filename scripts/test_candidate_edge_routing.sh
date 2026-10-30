#!/bin/bash
set -eo pipefail

echo "=========================================================="
echo "NHMP 130 - PHASE E: CANDIDATE EDGE STACK ROUTING TEST"
echo "=========================================================="

# 1. Port Binding Verification (Loopback only)
echo "[1] Checking Published Ports (Must be 127.0.0.1 only):"
ss -tulpn | grep -E ':(8080|8443|8405)\b' || true

# 2. HAProxy Direct Stats Check (Unauthorized vs Authorized)
echo -e "\n[2] Testing HAProxy Stats Endpoint on 127.0.0.1:8405/monitor:"
STATUS_UNAUTH=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8405/monitor || echo "FAIL")
echo "  - Unauthenticated Request HTTP Status : ${STATUS_UNAUTH} (Expected: 401)"

STATUS_AUTH=$(curl -s -o /dev/null -w "%{http_code}" -u "admin:CandidateStatsPass2026" http://127.0.0.1:8405/monitor || echo "FAIL")
echo "  - Authenticated Request HTTP Status   : ${STATUS_AUTH} (Expected: 200)"

# 3. Direct Health Endpoint Probe via HTTP on 127.0.0.1:8080/up
echo -e "\n[3] Testing Edge HTTP /up Endpoint on http://127.0.0.1:8080/up:"
HTTP_UP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/up || echo "FAIL")
echo "  - Edge HTTP /up Response Code         : ${HTTP_UP_STATUS} (Expected: 204)"

# 4. HTTPS Probe on 127.0.0.1:8443/up (TLS negotiation)
echo -e "\n[4] Testing Edge HTTPS /up Endpoint on https://127.0.0.1:8443/up:"
HTTPS_UP_STATUS=$(curl -k -s -o /dev/null -w "%{http_code}" https://127.0.0.1:8443/up || echo "FAIL")
echo "  - Edge HTTPS /up Response Code        : ${HTTPS_UP_STATUS} (Expected: 204)"

TLS_VERSION=$(curl -k -s -v https://127.0.0.1:8443/up 2>&1 | grep -i "SSL connection using" || true)
echo "  - TLS Negotiation Details             : ${TLS_VERSION}"

# 5. Roundrobin & SERVERID Cookie Verification across Backends
echo -e "\n[5] Testing Roundrobin Balancing across Candidate Web Nodes:"
COOKIES=()
for i in {1..6}; do
    COOKIE=$(curl -s -i http://127.0.0.1:8080/up | grep -i "Set-Cookie: SERVERID=" | tr -d '\r\n' || echo "NONE")
    COOKIES+=("${COOKIE}")
    echo "  - Request ${i}: ${COOKIE}"
done

# 6. Backend Failover & Dynamic Recovery Test
echo -e "\n[6] Testing Failover Resilience (Stopping candidate-web-2):"
docker stop 130-candidate-web-2 >/dev/null
sleep 2

FAILOVER_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/up || echo "FAIL")
FAILOVER_COOKIE=$(curl -s -i http://127.0.0.1:8080/up | grep -i "Set-Cookie: SERVERID=" | tr -d '\r\n' || echo "NONE")
echo "  - Status during web-2 outage           : ${FAILOVER_STATUS} (Traffic routed to: ${FAILOVER_COOKIE})"

echo "  - Restoring candidate-web-2:"
docker start 130-candidate-web-2 >/dev/null
sleep 4

RECOVERED_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/up || echo "FAIL")
echo "  - Status after web-2 restoration       : ${RECOVERED_STATUS}"

# 7. Operational DB Sequence Counter & Container Integrity Verification
echo -e "\n[7] Operational Environment Invariance Check:"
OP_SEQ=$(docker exec 130-db-1-1 mysql -u root -psecret -s -N -e "SELECT current_seq FROM 130_crm_db.call_sequences WHERE year=2026;" 2>/dev/null || echo "ERROR")
echo "  - Operational DB 130_crm_db.call_sequences: ${OP_SEQ} (Must remain frozen at 710035)"

echo "=========================================================="
echo "ROUTING & FAILOVER TESTS COMPLETE."
echo "=========================================================="
