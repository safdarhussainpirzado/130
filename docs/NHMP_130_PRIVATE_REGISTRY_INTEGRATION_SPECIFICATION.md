# NHMP 130 Helpline CRM — Enterprise Private Container Registry Integration Specification
**Document ID:** `NHMP-ENG-SPEC-REG-001`  
**Classification:** Internal Restricted  
**Authority:** National Highways & Motorway Police (NHMP) IT & Operations Command  
**Integration Status:** **BLOCKED** (Awaiting NHMP Intranet Registry Provisioning & Authorization)

---

## 1. Executive Summary & Purpose

This specification defines the security architecture, governance controls, authentication workflows, and infrastructure requirements for the **NHMP Private Container Registry** (`registry.nhmp.gov.pk` or internal Harbor / GitLab Container Registry). 

The NHMP 130 Helpline CRM platform relies on immutable, cryptographically verified container images. In local testing, image lifecycle management is simulated via an isolated local test registry (`localhost:5000`). However, promotion to operational staging and production is strictly **BLOCKED** until NHMP IT provisions an authorized intranet registry meeting the compliance controls detailed below.

---

## 2. Mandatory Registry Security & Operational Controls

The internal registry platform (Harbor v2.10+ / GitLab Registry / Quay) must enforce the following ten enterprise controls:

### 2.1 Transport Layer Security (TLS 1.3 / 1.2)
- Plaintext HTTP (port 80) is strictly prohibited. Port 80 must return a permanent 301 redirect to HTTPS.
- Certificates must be issued by the **NHMP Internal Root Certificate Authority**.
- Strong ciphers only (`TLS_AES_256_GCM_SHA384`, `TLS_CHACHA20_POLY1305_SHA256`).

### 2.2 Authentication & Role-Based Access Control (RBAC)
- Individual developer credentials or shared passwords must never be used in CI/CD.
- Dedicated machine **Robot Accounts** with least-privilege scoping:
  - `robot$nhmp-crm-ci-builder`: `PUSH` and `PULL` permissions restricted to repository `nhmp/130-app` and `nhmp/130-web`. Token TTL: 90 days.
  - `robot$nhmp-crm-staging-deployer`: `PULL-ONLY` permissions for `100.108.239.107`. Token TTL: 180 days.
  - `robot$nhmp-crm-prod-deployer`: `PULL-ONLY` permissions for `100.124.232.124`. Token TTL: 180 days.

### 2.3 Repository Protection & Tag Immutability
- Target repositories:
  - `registry.nhmp.gov.pk/nhmp/130-app` (PHP 8.3 FPM Application Runtime)
  - `registry.nhmp.gov.pk/nhmp/130-web` (Nginx Ingress Reverse Proxy)
- **Tag Immutability Rule:** All release tags (`v*`, `release-*`, and 7-character Git SHAs `7d89960`) must be marked **IMMUTABLE** in Harbor/GitLab. Re-pushing or overwriting an existing tag must be rejected with HTTP 409 Conflict.
- **Tag Deletion:** Restricted exclusively to NHMP Registry Super-Administrators.

### 2.4 Immutable Image Digest Deployment
- Deployments to Staging and Production must NEVER reference mutable tags like `:latest`.
- The CI/CD pipeline captures the immutable SHA-256 digest immediately upon publish:
  ```bash
  IMAGE_DIGEST=$(docker inspect --format='{{index .RepoDigests 0}}' "$REGISTRY_IMAGE:$CI_COMMIT_SHORT_SHA")
  # Example: registry.nhmp.gov.pk/nhmp/130-app@sha256:0fc032038455ff1150b5f68a5a9fa38fef3f267e3b209481f1b7cc181f7c5081
  ```
- Deployment playbooks pull and instantiate containers strictly by this `@sha256:...` digest.

### 2.5 Automated Vulnerability Scanning (Trivy / Harbor Scanner)
- Automated scanning triggered immediately upon layer upload.
- **Quality Gate Policy:**
  - `CRITICAL` Severity: **BLOCK PIPELINE** (Zero tolerance without formal NHMP CISO exception).
  - `HIGH` Severity: Allowed only if fix is not yet available and issue is isolated from network attack surfaces.
  - Automated CVE report exported as JSON artifact in GitLab pipeline.

### 2.6 Software Bill of Materials (SBOM) & Provenance
- Every built image must have an associated CycloneDX / SPDX SBOM generated via `syft`:
  ```bash
  syft "$IMAGE_TAG_SHA" -o cyclonedx-json=build-artifacts/sbom-${CI_COMMIT_SHORT_SHA}.json
  ```
- SLSA Provenance attestation generated and published alongside image layers.

### 2.7 Image Retention & Pruning Lifecycle
- Retain all release-tagged images (`v*`) indefinitely.
- Retain the most recent 30 commit-tagged images (`[0-9a-f]{7}`).
- Automatically prune untagged manifest lists older than 30 days.

### 2.8 Disaster Recovery & High Availability
- Registry storage backend backed by distributed S3-compatible storage (MinIO / Ceph).
- Daily snapshots of metadata database (PostgreSQL) and object storage buckets.
- **RPO:** `<= 1 hour`. **RTO:** `<= 2 hours`.

### 2.9 Centralized Audit Logging
- Every `docker login`, `docker push`, and `docker pull` event logged with client IP, timestamp, identity, and HTTP status code.
- Logs forwarded to NHMP central syslog/SIEM server.

### 2.10 Network Reachability Matrix
| Source Host | Destination Registry | Port | Direction | Access Policy |
| :--- | :--- | :--- | :--- | :--- |
| **GitLab Runner Host** | `registry.nhmp.gov.pk` | `443` | Outbound | Push & Pull (`robot$nhmp-crm-ci-builder`) |
| **Staging Host (`100.108.239.107`)** | `registry.nhmp.gov.pk` | `443` | Outbound | Pull Only (`robot$nhmp-crm-staging-deployer`) |
| **Production Host (`100.124.232.124`)**| `registry.nhmp.gov.pk` | `443` | Outbound | Pull Only (`robot$nhmp-crm-prod-deployer`) |

---

## 3. Removal of Localhost Registry Dependencies

All legacy hardcoded references to `localhost:5000` have been audited and replaced with environment-driven variables across the repository:

| Configuration Area | Legacy Parameter | Parameterized Enterprise Variable | Default Fallback |
| :--- | :--- | :--- | :--- |
| **GitLab CI/CD** | `localhost:5000` | `$REGISTRY_HOST` | `registry.nhmp.gov.pk` |
| **Repository Path** | `localhost:5000/nhmp/130-app` | `$REGISTRY_IMAGE` | `$REGISTRY_HOST/nhmp/130-app` |
| **Deployment Manifest**| `localhost:5000/...` | `image_digest: "$IMAGE_DIGEST"` | Exact `@sha256:...` digest |
| **Ansible Deploy** | Hardcoded tag | `{{ lookup('env', 'IMAGE_DIGEST') }}` | Validated digest manifest |
| **Compose Variable**| `130-app:latest` | `APP_IMAGE=${APP_IMAGE}` | Digest string |

---

## 4. Blocking Inputs Required from NHMP IT

To activate internal registry integration, NHMP IT must provide:

```
[ ] INPUT 1: FQDN and IP address of NHMP Container Registry (e.g., registry.nhmp.gov.pk)
[ ] INPUT 2: NHMP Internal Root CA Certificate for registry TLS trust
[ ] INPUT 3: CI/CD Builder Robot Account Username & Token (robot$nhmp-crm-ci-builder)
[ ] INPUT 4: Staging Pull-Only Robot Account Username & Token
[ ] INPUT 5: Production Pull-Only Robot Account Username & Token
[ ] INPUT 6: Firewall rules verified from Runner, 100.108.239.107, and 100.124.232.124 to Registry:443
```

**Status:** **BLOCKED** — Repository code and Ansible playbooks are fully parameterized and ready for immediate operational deployment upon receipt of these credentials.
