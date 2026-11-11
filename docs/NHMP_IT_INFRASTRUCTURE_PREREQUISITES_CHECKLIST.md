# NHMP 130 Helpline CRM — IT Infrastructure Prerequisites & Onboarding Checklist
**Document ID:** `NHMP-ENG-CHK-001`  
**Classification:** Internal Restricted  
**Authority:** National Highways & Motorway Police (NHMP) IT & Operations Command  
**Release Target:** NHMP 130 Helpline CRM Enterprise Deployment

---

## 1. Executive Summary & Purpose

Continuous Integration and Continuous Deployment (CI/CD) engineering for the **NHMP 130 Helpline CRM platform** is fully complete and empirically validated in the isolated candidate testbed (including 12-stage pipeline, zero-downtime rolling deployments, automated rollback, idempotency deduplication, and database disaster recovery).

However, live deployment to NHMP operational staging (`100.108.239.107`) and production (`100.124.232.124`) is strictly **BLOCKED** due to two external infrastructure dependencies that only NHMP IT can authorize and provision:
1. **No registered GitLab Runner inside the NHMP intranet.**
2. **No accessible NHMP private container registry (`registry.nhmp.gov.pk`).**

This document serves as the formal handover checklist detailing the exact technical specifications, credentials, and network requirements needed from NHMP IT to unblock enterprise release promotion.

---

## 2. Infrastructure Prerequisite Checklist

### Section A: Dedicated GitLab Runner Host (Intranet Zone)
| # | Requirement Item | Detailed Specification | NHMP IT Action Required | Status |
| :- | :--- | :--- | :--- | :---: |
| **A1** | **Host Provisioning** | 8 vCPU, 16 GB ECC RAM, 250 GB NVMe SSD, Ubuntu 24.04 LTS | Provision VM / bare-metal server on Intranet subnet | `[ ] PENDING` |
| **A2** | **Docker Runtime** | Docker Engine CE `>= 26.1` with `systemd` cgroups v2 | Install and enable `docker.service` | `[ ] PENDING` |
| **A3** | **GitLab Runner Agent**| `gitlab-runner` binary `>= 16.11` (RPM/DEB package) | Install runner package as systemd daemon | `[ ] PENDING` |
| **A4** | **Project Auth Token** | Format: `glrt-...` (Generated from GitLab UI CI/CD Runners) | Provide token for `nhmp/130-crm` project | `[ ] PENDING` |
| **A5** | **Runner Tags** | Tags: `nhmp-crm-runner`, `docker-internal`, `secure-build` | Apply tags during runner registration | `[ ] PENDING` |
| **A6** | **Protected Ref Scope** | Check `Protected: YES` (restricts to `main` and `staging` branches)| Configure in GitLab Runner Settings | `[ ] PENDING` |
| **A7** | **Root CA Certificate**| NHMP Internal Root CA (`nhmp-root-ca.crt`) | Copy to `/usr/local/share/ca-certificates/` | `[ ] PENDING` |
| **A8** | **Egress Firewall Rules**| Outbound TCP `443` to `gitlab.nhmp.gov.pk` and `registry.nhmp.gov.pk` | Whitelist in datacenter firewall matrix | `[ ] PENDING` |
| **A9** | **Deploy Firewall Rules**| Outbound TCP `22` to `100.108.239.107` and `100.124.232.124` | Whitelist in datacenter firewall matrix | `[ ] PENDING` |

---

### Section B: NHMP Private Container Registry (`registry.nhmp.gov.pk`)
| # | Requirement Item | Detailed Specification | NHMP IT Action Required | Status |
| :- | :--- | :--- | :--- | :---: |
| **B1** | **Registry FQDN & DNS**| `registry.nhmp.gov.pk` resolving on internal DNS (`10.0.0.x`) | Create internal A-record and reverse PTR | `[ ] PENDING` |
| **B2** | **TLS Certificate** | TLS 1.3 certificate issued by NHMP Internal CA | Bind TLS certificate to registry ingress | `[ ] PENDING` |
| **B3** | **Target Repositories**| `nhmp/130-app` (Application) and `nhmp/130-web` (Ingress Nginx) | Create project and repositories in registry | `[ ] PENDING` |
| **B4** | **CI Builder Robot** | Username: `robot$nhmp-crm-ci-builder` with Push & Pull rights | Generate scoped token (90-day TTL) | `[ ] PENDING` |
| **B5** | **Staging Deploy Robot**| Username: `robot$nhmp-crm-staging-deployer` with Pull-only rights | Generate scoped token for `100.108.239.107` | `[ ] PENDING` |
| **B6** | **Prod Deploy Robot** | Username: `robot$nhmp-crm-prod-deployer` with Pull-only rights | Generate scoped token for `100.124.232.124` | `[ ] PENDING` |
| **B7** | **Tag Immutability** | Immutability rules enabled on tags `v*`, `release-*`, `[0-9a-f]{7}` | Prevent tag overwriting in Harbor / GitLab | `[ ] PENDING` |
| **B8** | **Vulnerability Scan** | Trivy / Harbor Scanner auto-scan on push enabled | Enforce block on CRITICAL CVEs | `[ ] PENDING` |
| **B9** | **Storage Backend** | S3-compatible object store (MinIO/Ceph) with daily backup | Configure storage retention and snapshots | `[ ] PENDING` |

---

### Section C: Operational Staging Host Handshake (`100.108.239.107`)
| # | Requirement Item | Detailed Specification | NHMP IT Action Required | Status |
| :- | :--- | :--- | :--- | :---: |
| **C1** | **Deployer User** | System account: `deployer` (member of `docker` group) | Create user on `100.108.239.107` | `[ ] PENDING` |
| **C2** | **SSH Key Injection** | Dedicated ED25519 public key in `/home/deployer/.ssh/authorized_keys` | Authorize key; disable password login | `[ ] PENDING` |
| **C3** | **Registry Auth Login**| `docker login registry.nhmp.gov.pk` executed via robot token | Test pulling base Alpine and PHP images | `[ ] PENDING` |
| **C4** | **Staging Rehearsal** | Explicit authorization to execute rolling deploy rehearsal | Provide formal staging change window | `[ ] PENDING` |

---

### Section D: Production Release Gate Handshake (`100.124.232.124`)
| # | Requirement Item | Detailed Specification | NHMP IT Action Required | Status |
| :- | :--- | :--- | :--- | :---: |
| **D1** | **Zero Public Access** | No direct external internet routing to database or application ports | Verify perimeter firewall isolation | `[ ] PENDING` |
| **D2** | **Deployer Account** | System account: `deployer` with restricted sudo / docker permissions | Create user on `100.124.232.124` | `[ ] PENDING` |
| **D3** | **Production CA Trust**| NHMP Internal CA installed in host `/etc/docker/certs.d/` | Enable trust for private registry pulls | `[ ] PENDING` |
| **D4** | **CAB Approval** | Formal Change Advisory Board (CAB) signed release form | Execute sign-off prior to production run | `[ ] PENDING` |

---

## 3. Handover Protocol & Execution Sequence

Upon completion of the above checklist by NHMP IT, the deployment pipeline will be activated according to this sequence:

```
[Phase 1: Registration]
  1. Register Runner with glrt-... token on runner host.
  2. Verify runner status: gitlab-runner verify (Status: Alive).
       │
       ▼
[Phase 2: Registry Handshake]
  3. Execute automated test push of 130-app to registry.nhmp.gov.pk.
  4. Verify Trivy vulnerability scan and SBOM CycloneDX generation.
       │
       ▼
[Phase 3: Staging Rehearsal (100.108.239.107)]
  5. Run Ansible rolling deploy to Staging using immutable digest.
  6. Execute 100% automated browser & UAT smoke tests against Staging.
  7. Execute automated rollback rehearsal on Staging node.
       │
       ▼
[Phase 4: Production Gate (100.124.232.124)]
  8. Present Staging verification evidence to NHMP Change Advisory Board.
  9. Obtain formal manual approval via GitLab protected environment gate.
 10. Execute zero-downtime rolling production release.
```
