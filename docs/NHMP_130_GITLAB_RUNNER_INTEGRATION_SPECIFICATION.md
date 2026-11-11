# NHMP 130 Helpline CRM — Enterprise GitLab Runner Integration Specification
**Document ID:** `NHMP-ENG-SPEC-CICD-001`  
**Classification:** Internal Restricted  
**Authority:** National Highways & Motorway Police (NHMP) IT & Operations Command  
**Integration Status:** **BLOCKED** (Awaiting NHMP Intranet Infrastructure Provisioning)

---

## 1. Executive Summary & Purpose

This specification defines the formal engineering, architectural, and operational security requirements for deploying and integrating an intranet-hosted **GitLab Runner** dedicated to the **NHMP 130 Helpline CRM platform**. 

Currently, all pipeline stages, zero-downtime rolling deployments, and failure-recovery controls have been fully engineered and validated locally in the isolated development testbed. However, actual continuous integration and release delivery within the NHMP enterprise network is **BLOCKED** pending the provisioning of an internal runner host and registration by NHMP IT.

---

## 2. Infrastructure Requirements & Host Sizing

The dedicated runner host must run on dedicated or virtualized enterprise Linux inside the secure NHMP datacenter / intranet zone.

### 2.1 Hardware Specifications
| Component | Minimum Specification | Recommended Production Specification | Rationale |
| :--- | :--- | :--- | :--- |
| **CPU Architecture** | x86_64 (64-bit) | x86_64 (64-bit, Intel Xeon or AMD EPYC) | Docker layer building & PHPStan analysis |
| **Virtual Cores** | 4 vCPUs | 8 vCPUs | Parallel testing, Trivy scans, asset compilation |
| **System Memory** | 8 GB RAM | 16 GB ECC RAM | In-memory SQLite/MySQL feature tests, Composer caching |
| **Disk Storage** | 100 GB SSD/NVMe | 250 GB NVMe (RAID-1/10) | Multi-stage Docker build cache, image archives, logs |
| **Network Interface**| 1 Gbps NIC | 10 Gbps Redundant NICs | Rapid push/pull of Docker image layers (800MB+) |
| **Operating System** | Ubuntu 24.04 LTS / RHEL 9 | Ubuntu 24.04 LTS / Rocky Linux 9.4 | Long Term Support, kernel 6.x+, systemd v255+ |

### 2.2 Host OS Hardening & Kernel Configuration
- **Kernel version:** `>= 5.15` with cgroups v2 enabled (`systemd.unified_cgroup_hierarchy=1`).
- **File descriptors:** `fs.file-max = 2097152` in `/etc/sysctl.conf`.
- **Inotify limits:** `fs.inotify.max_user_watches = 524288`.
- **AppArmor / SELinux:** Enforcing mode with strict Docker container profile.
- **SSH Access:** SSHv2 with ED25519 public-key authentication only. Password authentication disabled (`PasswordAuthentication no`). Root login disabled (`PermitRootLogin no`).

---

## 3. GitLab Version & Runner Registration Workflow

GitLab deprecated legacy registration tokens (`CI_RUNNER_REGISTRATION_TOKEN`) in GitLab 16.0. NHMP IT must use the current **GitLab Runner Authentication Token (`glrt-...`)** workflow.

### 3.1 Prerequisite GitLab Platform
- **Minimum GitLab Version:** GitLab CE / EE `>= 16.5` (Recommended: `>= 17.0`).
- **GitLab Architecture:** Self-hosted on NHMP Intranet (e.g., `https://gitlab.nhmp.gov.pk`).

### 3.2 Token Generation Procedure (NHMP IT Administrator)
1. In GitLab UI, navigate to **Project: NHMP 130 Helpline CRM** -> **Settings** -> **CI/CD** -> **Runners**.
2. Click **New project runner** (or create a Group-level runner under the NHMP group).
3. Specify Operating System: **Linux**.
4. Configure Runner Tags: `nhmp-crm-runner`, `docker-internal`, `secure-build`.
5. Check **Lock to current projects** (prevents unauthorized project job execution).
6. Check **Protected** (ensures runner only executes on protected branches `main`, `staging` and release tags).
7. Click **Create runner**.
8. Copy the generated **Runner Authentication Token** (format: `glrt-xxxxxxxxxxxxxxxxxxxx`).

---

## 4. Docker Executor Configuration (`config.toml`)

The runner must use the **Docker executor** with strictly controlled volume mounts, local caching, and secure isolation.

### 4.1 Production Configuration File: `/etc/gitlab-runner/config.toml`

```toml
concurrent = 4
check_interval = 5
shutdown_timeout = 60

[session_server]
  session_timeout = 1800

[[runners]]
  name = "nhmp-crm-runner-01.intranet.nhmp.gov.pk"
  url = "https://gitlab.nhmp.gov.pk"
  id = 130
  token = "glrt-REPLACE_WITH_ACTUAL_NHMP_TOKEN"
  token_obtained_at = "2026-11-02T00:00:00Z"
  token_expires_at = "0001-01-01T00:00:00Z"
  executor = "docker"
  tags = ["nhmp-crm-runner", "docker-internal", "secure-build"]
  run_untagged = false
  access_level = "ref_protected"

  [runners.custom_build_dir]
    enabled = false

  [runners.cache]
    MaxUploadedArchiveSize = 524288000
    [runners.cache.dir]
      Path = "/var/lib/gitlab-runner/cache"

  [runners.docker]
    tls_verify = true
    image = "docker:26.1-cli"
    privileged = false
    disable_entrypoint_overwrite = false
    oom_kill_disable = false
    disable_cache = false
    volumes = [
      "/var/run/docker.sock:/var/run/docker.sock:rw",
      "/var/lib/gitlab-runner/cache:/cache:rw",
      "/usr/local/share/ca-certificates/nhmp-root-ca.crt:/etc/ssl/certs/nhmp-root-ca.crt:ro",
      "/etc/docker/certs.d/registry.nhmp.gov.pk:/etc/docker/certs.d/registry.nhmp.gov.pk:ro"
    ]
    shm_size = 2147483648
    pull_policy = ["if-not-present"]
    network_mode = "bridge"
```

### 4.2 Security Architecture Rationale
- `privileged = false`: Avoids root container breakouts. Container builds execute via host Docker socket bind (`/var/run/docker.sock`), governed by host Docker daemon controls.
- `access_level = "ref_protected"`: Strictly enforces that jobs on feature/unreviewed branches CANNOT access deployment credentials or execute on this runner.
- `volumes`: Injects internal NHMP Root CA certificates into build containers for airgapped TLS verification.

---

## 5. Network Connectivity, DNS & Firewall Matrix

The runner host operates within the NHMP Intranet. External public internet access is **NOT required** if local mirrors/caches are maintained.

| Source | Destination | Protocol / Port | Purpose | Security Rule |
| :--- | :--- | :--- | :--- | :--- |
| **Runner Host** | `gitlab.nhmp.gov.pk` | TCP `443` (HTTPS) | CI/CD Job polling, artifact uploads | State: Established, TLS 1.3 |
| **Runner Host** | `registry.nhmp.gov.pk` | TCP `443` (HTTPS) | Base image pull, release image push | Mutual TLS / Basic Auth |
| **Runner Host** | `100.108.239.107` (Staging) | TCP `22` (SSH) | Ansible staging rolling deploy | SSH Key Whitelist |
| **Runner Host** | `100.124.232.124` (Production)| TCP `22` (SSH) | Ansible production rolling deploy | Multi-factor Bastion + SSH Key |
| **Runner Host** | Internal NTP (`ntp.nhmp.gov.pk`)| UDP `123` (NTP) | System clock synchronization | Drift < 50ms |
| **Runner Host** | Internal DNS (`10.0.0.2`)| UDP/TCP `53` (DNS) | Name resolution for intranet services| Internal recursive DNS |

---

## 6. Service Account & Secret Management

1. **System Service Account:**
   - Runner daemon runs under dedicated user `gitlab-runner` (UID `998`, GID `998`).
   - Sudo access: Strictly restricted or disabled. No passwordless full sudo (`ALL=(ALL) NOPASSWD: ALL` is strictly prohibited).
2. **GitLab CI/CD Variable Security:**
   - All production secrets (`PROD_SSH_KEY`, `DB_PASSWORD`, `REGISTRY_PASSWORD`) must be stored in GitLab CI/CD Variables with:
     - **Type:** Variable or File.
     - **Flags:** `Protected: YES` (only exposed to `main` branch), `Masked: YES` (prevents display in logs), `Expanded: NO`.
3. **Secret Rotation Policy:**
   - Registry robot tokens: 90-day rotation.
   - Deploy SSH keys: 180-day rotation.
   - Runner auth token: 365-day rotation.

---

## 7. Operational Monitoring & Health Verification

- **Prometheus Metrics:** GitLab Runner exposes runtime telemetry on `http://127.0.0.1:9252/metrics`.
- **Monitored Metrics:**
  - `gitlab_runner_jobs_total`: Total completed jobs by stage.
  - `gitlab_runner_failed_jobs_total`: Pipeline failure alerts.
  - `gitlab_runner_concurrent_jobs`: Capacity utilization.
- **Log Aggregation:**
  - System logs collected via systemd journald: `journalctl -u gitlab-runner -f`.
  - Forwarded to NHMP centralized SIEM (Graylog / ELK).

---

## 8. Blocking Dependencies & Exact Inputs Required from NHMP IT

This integration cannot proceed to live intranet operation until NHMP IT provides:

```
[ ] INPUT 1: GitLab Instance Base URL (e.g., https://gitlab.nhmp.gov.pk)
[ ] INPUT 2: GitLab Project Runner Authentication Token (format: glrt-...)
[ ] INPUT 3: Designated Runner Host IP address & SSH credentials on NHMP Intranet
[ ] INPUT 4: NHMP Internal Root CA Certificate (nhmp-root-ca.crt)
[ ] INPUT 5: Network firewall approval connecting Runner -> GitLab, Registry, Staging Host
```

**Status:** **BLOCKED** — Ready for immediate automated registration upon receipt of above 5 inputs.
