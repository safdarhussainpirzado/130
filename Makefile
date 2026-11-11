# ==============================================================================
# NATIONAL HIGHWAYS & MOTORWAY POLICE (NHMP) 130 HELPLINE
# Phase 2 Unified Test Architecture, Verification & Operational Makefile
# ==============================================================================

# Include Modular Configuration and Target Definitions
include make/common.mk
include make/docker.mk
include make/tests.mk
include make/security.mk
include make/database.mk
include make/infrastructure.mk
include make/performance.mk
include make/evidence.mk
include make/deploy.mk

.DEFAULT_GOAL := help
.PHONY: help bash up down restart deploy rolling-deploy rollback assets clear_prod


# Exec inside Primary Application Container
bash:
	docker exec -it $(APP1_CONTAINER) sh

# Production Deployment Trigger (Strictly Guarded)
deploy:
	$(call guard-not-production)
	$(call guard-confirm,Execute deployment script,yes-deploy)
	./deploy.sh

# Sync build assets from host to container
assets: sync-assets

# Safe cache clear & optimize
clear_prod: sync-clear

# ==============================================================================
# Comprehensive Help & Command Catalog
# ==============================================================================
help:
	@echo -e "$(CLR_BOLD)================================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 HELPLINE CRM & TABLET ADVISORY PLATFORM — COMMAND DIRECTORY$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)================================================================================$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)Usage:$(CLR_RESET) make $(CLR_GREEN)<target>$(CLR_RESET) [ENV=local|test|staging|production] [CONFIRM=yes-...]"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)A. Stack Lifecycle & Zero-Downtime Deployment:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)up$(CLR_RESET)                       Start all Docker Compose stack services"
	@echo -e "  $(CLR_GREEN)down$(CLR_RESET)                     Stop all Docker Compose stack services"
	@echo -e "  $(CLR_GREEN)restart$(CLR_RESET)                  Restart complete Docker Compose stack"
	@echo -e "  $(CLR_GREEN)rolling-deploy$(CLR_RESET)           Execute controlled zero-downtime rolling release (HAProxy + App Nodes)"
	@echo -e "  $(CLR_GREEN)rollback$(CLR_RESET)                 Execute rapid emergency rollback to baseline containers"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)B. Environment Diagnostics & Health:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)help$(CLR_RESET)                     Show this comprehensive command directory and explanations"
	@echo -e "  $(CLR_GREEN)env$(CLR_RESET)                      Display active environment, target IPs, and security policy"
	@echo -e "  $(CLR_GREEN)health$(CLR_RESET)                   Verify all required containers and HTTP ingress health endpoints"
	@echo -e "  $(CLR_GREEN)containers$(CLR_RESET)               Show container names, status, health, and port bindings"
	@echo -e "  $(CLR_GREEN)versions$(CLR_RESET)                 Show PHP, Laravel, MySQL, Redis, Node, and Composer versions"
	@echo -e "  $(CLR_GREEN)sync-check$(CLR_RESET)               Compare SHA-256 checksums of core files across app-1 and app-2"
	@echo -e "  $(CLR_GREEN)logs$(CLR_RESET)                     Follow HAProxy edge load balancer logs"
	@echo -e "  $(CLR_GREEN)logs-app$(CLR_RESET)                 Follow primary application container logs (app-1)"
	@echo -e "  $(CLR_GREEN)logs-db$(CLR_RESET)                  Follow MySQL primary database container logs (db-1)"
	@echo -e "  $(CLR_GREEN)logs-redis$(CLR_RESET)               Follow Redis primary in-memory container logs"
	@echo -e "  $(CLR_GREEN)logs-workers$(CLR_RESET)             Inspect running Supervisor worker processes and queue assignments"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)B. Application Synchronization:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)sync$(CLR_RESET)                     Synchronize application code into app-1 and app-2 (no restart)"
	@echo -e "  $(CLR_GREEN)sync-assets$(CLR_RESET)              Synchronize frontend build assets into web and app nodes"
	@echo -e "  $(CLR_GREEN)sync-clear$(CLR_RESET)               Synchronize code and safely rebuild Laravel caches on all nodes"
	@echo -e "  $(CLR_GREEN)optimize$(CLR_RESET)                 Warm optimized Laravel framework caches across all app nodes"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)C. Unit, Integration & Regression Testing:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)test$(CLR_RESET)                     Run default test suite (Unit test suite)"
	@echo -e "  $(CLR_GREEN)test-unit$(CLR_RESET)                Run full Unit test suite (113 tests: Models, Guards, Sequence)"
	@echo -e "  $(CLR_GREEN)test-feature$(CLR_RESET)             Run Feature test suite"
	@echo -e "  $(CLR_GREEN)test-integration$(CLR_RESET)         Run Outbox and Scope isolation integration suites"
	@echo -e "  $(CLR_GREEN)test-regression$(CLR_RESET)          Run 12 targeted verification and data-minimization regression tests"
	@echo -e "  $(CLR_GREEN)test-all$(CLR_RESET)                 Execute full test inventory gate with complete log accounting"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)D. Security Testing & Quality Gates:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)test-security$(CLR_RESET)            Run MFA, session, and isolation security unit/feature tests"
	@echo -e "  $(CLR_GREEN)test-security-regression$(CLR_RESET) Run live attack suite (SQLi, IDOR, RBAC, CSRF, ETag poisoning)"
	@echo -e "  $(CLR_GREEN)security-audit$(CLR_RESET)           Run Composer and NPM dependency vulnerability audits"
	@echo -e "  $(CLR_GREEN)security-static$(CLR_RESET)          Verify no 777 permissions and zero hardcoded passwords in configs"
	@echo -e "  $(CLR_GREEN)quality-gate$(CLR_RESET)             Execute full security quality gate (fails fast on any defect)"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)E. Database & Replication Verification:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)db-health$(CLR_RESET)                Verify MySQL primary and replica ping connectivity"
	@echo -e "  $(CLR_GREEN)db-status$(CLR_RESET)                Display primary/replica identities, buffer pool, and durability"
	@echo -e "  $(CLR_GREEN)db-replication-check$(CLR_RESET)     Verify GTID sync, IO/SQL running state, and lag on db-2 and db-3"
	@echo -e "  $(CLR_GREEN)db-schema-check$(CLR_RESET)          Inspect Laravel database migration status table"
	@echo -e "  $(CLR_GREEN)db-index-check$(CLR_RESET)           Verify composite performance indexes on calls table"
	@echo -e "  $(CLR_GREEN)db-backup-verify$(CLR_RESET)         Verify integrity of baseline database backup archives via gzip"
	@echo -e "  $(CLR_GREEN)migrate$(CLR_RESET)                  Run pending database migrations (Guarded against production)"
	@echo -e "  $(CLR_GREEN)fresh$(CLR_RESET)                    [DESTRUCTIVE] Wipe and re-seed database (Requires CONFIRM)"
	@echo -e "  $(CLR_GREEN)migrate-legacy$(CLR_RESET)           [DESTRUCTIVE] Run legacy data migration (Requires CONFIRM)"
	@echo -e "  $(CLR_GREEN)bootstrap-db$(CLR_RESET)             [DESTRUCTIVE] Bootstrap MySQL cluster (Requires CONFIRM)"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)F. Queue Workers & Supervisor Verification:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)queue-status$(CLR_RESET)             Display running Supervisor worker processes and queue names"
	@echo -e "  $(CLR_GREEN)queue-health$(CLR_RESET)             Verify all 13 worker processes are active and healthy"
	@echo -e "  $(CLR_GREEN)queue-drain-check$(CLR_RESET)        Audit Redis queue backlog and failed jobs in failed_jobs table"
	@echo -e "  $(CLR_GREEN)queue-test$(CLR_RESET)               Dispatch a test queue job through worker infrastructure"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)G. Redis & Sentinel High Availability:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)redis-health$(CLR_RESET)             Ping Redis primary, replica, and all 3 Sentinel instances"
	@echo -e "  $(CLR_GREEN)redis-status$(CLR_RESET)             Display Redis topology, role status, and Sentinel master discovery"
	@echo -e "  $(CLR_GREEN)redis-replication-check$(CLR_RESET)  Compare primary vs replica replication offset delta"
	@echo -e "  $(CLR_GREEN)test-redis-failover$(CLR_RESET)      Execute automated primary termination and Sentinel failover test"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)H. Performance, Concurrency & Soak Testing:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)test-performance$(CLR_RESET)        Run baseline performance verification test"
	@echo -e "  $(CLR_GREEN)test-load$(CLR_RESET)               Run automated tiered HTTP load test matrix (100 -> 2,500 VUs)"
	@echo -e "  $(CLR_GREEN)test-load-100$(CLR_RESET)           Execute genuine HTTP load test tier: 100 VUs"
	@echo -e "  $(CLR_GREEN)test-load-250$(CLR_RESET)           Execute genuine HTTP load test tier: 250 VUs"
	@echo -e "  $(CLR_GREEN)test-load-500$(CLR_RESET)           Execute genuine HTTP load test tier: 500 VUs"
	@echo -e "  $(CLR_GREEN)test-load-1000$(CLR_RESET)          Execute genuine HTTP load test tier: 1,000 VUs"
	@echo -e "  $(CLR_GREEN)test-load-2500$(CLR_RESET)          Execute genuine HTTP load test tier: 2,500 VUs"
	@echo -e "  $(CLR_GREEN)test-load-5000$(CLR_RESET)          Execute extreme stress tier: 5,000 VUs (Requires CONFIRM)"
	@echo -e "  $(CLR_GREEN)test-load-10000$(CLR_RESET)         Execute extreme stress tier: 10,000 VUs (Requires CONFIRM)"
	@echo -e "  $(CLR_GREEN)test-crm-stress$(CLR_RESET)         Execute emergency CRM concurrent transactional dispatch test"
	@echo -e "  $(CLR_GREEN)test-combined$(CLR_RESET)           Run simultaneous CRM transactional dispatches and tablet polling"
	@echo -e "  $(CLR_GREEN)test-soak$(CLR_RESET)               Run extended soak test (Default 2h, configurable via SOAK_DURATION)"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)I. Real-Time SSE & RoadRunner Scope Isolation:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)test-sse$(CLR_RESET)                Verify live Server-Sent Events push stream delivery"
	@echo -e "  $(CLR_GREEN)test-sse-reconnect$(CLR_RESET)      Verify client reconnection with Last-Event-ID header"
	@echo -e "  $(CLR_GREEN)test-roadrunner$(CLR_RESET)         Verify process engine and PCNTL/POSIX extension health"
	@echo -e "  $(CLR_GREEN)test-scope-isolation$(CLR_RESET)    Verify worker state cleanup and zero authentication scope leakage"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)J. Evidence Compilation & Audit Dossier:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)evidence$(CLR_RESET)                 Compile full timestamped evidence dossier in artifacts/YYYY-MM-DD/"
	@echo -e "  $(CLR_GREEN)evidence-infrastructure$(CLR_RESET)  Collect container, worker, proxy, and PHP-FPM runtime evidence"
	@echo -e "  $(CLR_GREEN)evidence-database$(CLR_RESET)        Collect MySQL primary and multi-replica replication evidence"
	@echo -e "  $(CLR_GREEN)evidence-security$(CLR_RESET)        Collect security attack results, audits, and permission checks"
	@echo -e "  $(CLR_GREEN)evidence-performance$(CLR_RESET)     Collect HTTP load test results and CRM stress metrics"
	@echo -e "  $(CLR_GREEN)report$(CLR_RESET)                   Display executive technical readiness and audit summary"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)K. Database Snapshot & Local Refresh:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)refresh-local-db-dry-run$(CLR_RESET)          Display proposed workflow without connecting or mutating data"
	@echo -e "  $(CLR_GREEN)refresh-local-db-from-production$(CLR_RESET)  Obtain authorized sanitized production snapshot and refresh local DB"
	@echo -e "  $(CLR_GREEN)refresh-local-db-verify$(CLR_RESET)           Verify restored local database (45 tables, FKs, sequences, smoke test)"
	@echo -e "  $(CLR_GREEN)refresh-local-db-backup$(CLR_RESET)           Create verified gzip backup of current local development database"
	@echo -e "  $(CLR_GREEN)refresh-local-db-restore$(CLR_RESET)          Restore a previously downloaded and verified local snapshot"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)L. Staging Deployment:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)deploy-staging$(CLR_RESET)                  Trigger guarded staging deployment pipeline (Requires ENV=staging CONFIRM=deploy-staging)"
	@echo -e "  $(CLR_GREEN)rolling-deploy-staging$(CLR_RESET)          Execute guarded zero-downtime rolling release on staging (Requires ENV=staging CONFIRM=deploy-staging)"
	@echo -e "  $(CLR_GREEN)rollback-staging$(CLR_RESET)                Execute rapid emergency rollback to previous baseline on staging (Requires ENV=staging CONFIRM=deploy-staging)"
	@echo -e "  $(CLR_GREEN)staging-health$(CLR_RESET)                  Verify staging health and architecture policy"
	@echo ""
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)M. Production Deployment:$(CLR_RESET)"
	@echo -e "  $(CLR_GREEN)deploy-production$(CLR_RESET)              Deploy immutable image digest to production (Requires CONFIRM=deploy-production)"
	@echo -e "  $(CLR_GREEN)rolling-deploy-production$(CLR_RESET)      Execute guarded production rolling release (Requires CONFIRM=deploy-production)"
	@echo -e "  $(CLR_GREEN)rollback-production$(CLR_RESET)            Execute guarded production emergency rollback (Requires CONFIRM=deploy-production)"
	@echo -e "  $(CLR_GREEN)production-health$(CLR_RESET)              Verify production architecture definition and isolation status"
	@echo -e "  $(CLR_CYAN)* Remote deployment commands require explicit authorization. Local verification does not constitute remote execution.*$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)================================================================================$(CLR_RESET)"

