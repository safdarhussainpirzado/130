# NHMP 130 HELPLINE — HAPROXY CANDIDATE VALIDATION PLAN

## Document Metadata
- **Document ID:** NHMP-130-SEC-PLAN-HAP-006
- **Target Repository:** `/home/mrpirzado/projects/130`
- **Target Host:** Staging `100.108.239.107`
- **Stopped Live Edge Containers:** `130-haproxy-1`, `130-web-1-1`, `130-web-2-1` (Exited 255)
- **Classification:** Controlled Engineering Artifact / Edge Architecture Validation Plan
- **Status:** READY FOR REVIEW — ISOLATED COMPOSE SPECIFICATION DEFINED

---

## 1. Forensic Audit of Live Edge Services & Failure Root Cause

On the staging host (`100.108.239.107`), Docker process inspection confirmed that all live edge ingress containers are stopped:
```
CONTAINER ID   IMAGE            COMMAND                  STATUS                     NAMES
deeb47ebba4f   nginx:alpine     "/docker-entrypoint.…"   Exited (255) 4 hours ago   130-web-1-1
978ee2ba07a2   nginx:alpine     "/docker-entrypoint.…"   Exited (255) 4 hours ago   130-web-2-1
b7cd4bfe7632   haproxy:alpine   "docker-entrypoint.s…"   Exited (255) 4 hours ago   130-haproxy-1
```

### 1.1 Forensic Root Cause of HAProxy Startup Abort
Syntax check (`haproxy -c -f /usr/local/etc/haproxy/haproxy.cfg`) succeeds when certificates are mounted. However, **runtime startup fails with fatal error if `web-1` and `web-2` are not active and resolvable**:
```
[ALERT] 301/211428 (1) : parsing [/usr/local/etc/haproxy/haproxy.cfg:87] : 'server web-1' : could not resolve address 'web-1'.
[ALERT] 301/211428 (1) : Failed to initialize server(s) addr.
```
**Technical Root Cause:**
By default in HAProxy, server directives (`server web-1 web-1:80 check`) resolve hostnames at configuration parse time during process boot. If the target containers `web-1` and `web-2` are stopped, the Docker internal DNS server (`127.0.0.11`) has no DNS records for them. Because HAProxy cannot resolve the IP address at startup, it exits immediately with code 1.

Prior reports that used temporary `/etc/hosts` mappings proved configuration syntax, but **DID NOT PROVE RUNTIME STARTUP IN DOCKER COMPOSE**.

### 1.2 Security Deficiencies Remediated in Configuration
1. **Hardcoded Credentials Purged:** Earlier iterations contained default/hardcoded passwords in `haproxy.cfg` for the statistics endpoint. These have been purged and replaced with strict environment injection (`${HAPROXY_STATS_PASSWORD:?Error: HAPROXY_STATS_PASSWORD must be set}`).
2. **TLS Certificate Binding:** `haproxy.pem` (combining certificate and private key) is mounted read-only (`:ro`) into container path `/usr/local/etc/haproxy/certs/haproxy.pem`.

---

## 2. Mandatory Boundary: Live Edge Services Remain Stopped

> [!IMPORTANT]
> **PROHIBITION:** The stopped staging edge services (`130-web-1-1`, `130-web-2-1`, `130-haproxy-1`) **MUST NOT BE STARTED, RESTARTED, OR REPLACED** as part of this remediation phase.
> Starting live edge services would expose unverified application endpoints to public network interfaces and risk port conflicts with existing services.

---

## 3. Isolated Candidate Validation Stack Design

To validate HAProxy with genuine Docker Compose DNS resolution without affecting the active staging stack, an independent candidate stack (`docker-compose.candidate-haproxy.yml`) is designed:

### 3.1 Non-Conflicting Port Allocation
The candidate stack uses isolated non-conflicting host ports:
- Candidate HTTP: `127.0.0.1:8080` (Active uses 80)
- Candidate HTTPS: `127.0.0.1:8443` (Active uses 443)
- Candidate Stats: `127.0.0.1:8405` (Active uses 8404)
- Candidate Live Ingress: `127.0.0.1:7088` (Active uses 7085)
- Candidate Secondary: `127.0.0.1:8088` (Active uses 8085)

### 3.2 Dynamic DNS Resolution Architecture
To solve the boot-time DNS resolution failure without requiring hardcoded IP addresses or artificial `/etc/hosts` overrides, HAProxy configuration must incorporate Docker's internal resolver and `init-addr`:

```haproxy
resolvers docker_dns
    nameserver dns1 127.0.0.11:53
    resolve_retries 3
    timeout retry 1s
    hold valid 10s

backend web_cluster
    mode http
    balance roundrobin
    cookie NHMP_SERVER_ID insert indirect nocache secure httponly
    server web-1 candidate-web-1:80 check resolvers docker_dns init-addr libc,none
    server web-2 candidate-web-2:80 check resolvers docker_dns init-addr libc,none
```
With `init-addr libc,none`, HAProxy initializes even if backend containers boot slightly after HAProxy, dynamically resolving addresses as containers come online.

---

## 4. Candidate Validation Execution Matrix

| Step ID | Verification Target | Test Method & Assertion | Exit Criteria |
| :--- | :--- | :--- | :--- |
| **VAL-HA-01** | **DNS Resolution** | Launch `candidate-web-1`, `candidate-web-2`, and `candidate-haproxy` on isolated Docker network `candidate-net`. | HAProxy container status is `Up` (exit code 0); Docker DNS successfully resolves backends. |
| **VAL-HA-02** | **TLS Handshake** | Execute `curl -k -v https://127.0.0.1:8443/up`. | TLS handshake succeeds; returns HTTP 200/204; TLS version TLSv1.3 negotiated. |
| **VAL-HA-03** | **Health Check & Load Balancing** | Send 20 requests to `/up`. | Requests alternate evenly between `candidate-web-1` and `candidate-web-2`; cookie `NHMP_SERVER_ID` set. |
| **VAL-HA-04** | **Stats Endpoint Authentication** | `curl -i http://127.0.0.1:8405/haproxy?stats` without credentials, then with valid injected credentials. | Returns HTTP 401 Unauthorized without auth; returns HTTP 200 OK with valid credentials. |
| **VAL-HA-05** | **Zero Secret Leakage** | Inspect HAProxy status output and logs. | Environment variables containing secrets are redacted; no plaintext passwords printed. |
| **VAL-HA-06** | **Stack Teardown** | `docker compose -f docker-compose.candidate-haproxy.yml down -v`. | Ephemeral candidate containers and networks removed cleanly without touching staging services. |

---

## 5. Administrative Approval Boundary

> [!WARNING]
> Validating the candidate Compose stack requires Docker permissions on the staging host. While designed to be 100% isolated on non-conflicting loopback ports (`127.0.0.1`), executing `docker compose up` for the candidate stack requires **explicit operational approval** to guarantee no interference with running services `nhmp_hms_*` or active database `130-db-1-1`.
