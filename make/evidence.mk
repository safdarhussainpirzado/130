# ==============================================================================
# NHMP 130 - Evidence Dossier & Audit Reporting Module
# ==============================================================================

.PHONY: evidence evidence-infrastructure evidence-database evidence-security evidence-performance report

# Master Evidence Collection Target
evidence: evidence-infrastructure evidence-database evidence-security evidence-performance report
	@echo -e "\n$(CLR_GREEN)$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "$(CLR_GREEN)$(CLR_BOLD)  ALL EVIDENCE ARTIFACTS COMPILED IN $(EVIDENCE_BASE_DIR)/$(CLR_RESET)"
	@echo -e "$(CLR_GREEN)$(CLR_BOLD)======================================================================$(CLR_RESET)"

# Infrastructure Evidence Collection
evidence-infrastructure:
	@echo -e "$(CLR_BOLD)==> Collecting Infrastructure Evidence...$(CLR_RESET)"
	@mkdir -p $(EVIDENCE_BASE_DIR)/infrastructure $(EVIDENCE_BASE_DIR)/environment $(EVIDENCE_BASE_DIR)/queues $(EVIDENCE_BASE_DIR)/redis
	@echo "Timestamp: $(TIMESTAMP)" > $(EVIDENCE_BASE_DIR)/environment/host_identity.txt
	@hostname -f >> $(EVIDENCE_BASE_DIR)/environment/host_identity.txt && uname -a >> $(EVIDENCE_BASE_DIR)/environment/host_identity.txt
	@docker ps -a --format "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}" > $(EVIDENCE_BASE_DIR)/infrastructure/containers.txt
	@docker exec $(APP1_CONTAINER) supervisorctl status > $(EVIDENCE_BASE_DIR)/queues/supervisor_workers.txt 2>&1 || true
	@docker exec $(REDIS_PRIMARY_CONTAINER) redis-cli info replication > $(EVIDENCE_BASE_DIR)/redis/primary_replication.txt 2>&1 || true
	@docker exec $(REDIS_REPLICA_CONTAINER) redis-cli info replication > $(EVIDENCE_BASE_DIR)/redis/replica_replication.txt 2>&1 || true
	@docker exec $(SENTINEL1_CONTAINER) redis-cli -p 26379 sentinel master mymaster > $(EVIDENCE_BASE_DIR)/redis/sentinel_master.txt 2>&1 || true
	@docker exec $(APP1_CONTAINER) grep -E "^pm\." /usr/local/etc/php-fpm.d/www.conf > $(EVIDENCE_BASE_DIR)/infrastructure/php_fpm_app1.txt 2>&1 || true
	@echo -e "  [$(CLR_GREEN)OK$(CLR_RESET)] Infrastructure artifacts stored in $(EVIDENCE_BASE_DIR)/infrastructure"

# Database & Replication Evidence Collection
evidence-database:
	@echo -e "$(CLR_BOLD)==> Collecting Database & Replication Evidence...$(CLR_RESET)"
	@mkdir -p $(EVIDENCE_BASE_DIR)/database
	@docker exec $(DB1_CONTAINER) mysql -u root -p"$(DB_ROOT_PASS)" -e "SELECT @@server_id, @@server_uuid, @@gtid_executed; SHOW VARIABLES WHERE Variable_name IN ('innodb_buffer_pool_size', 'innodb_flush_log_at_trx_commit', 'sync_binlog');" > $(EVIDENCE_BASE_DIR)/database/primary_config.txt 2>&1 || true
	@docker exec $(DB2_CONTAINER) mysql -u root -p"$(DB_ROOT_PASS)" -e "SHOW REPLICA STATUS\G" > $(EVIDENCE_BASE_DIR)/database/replica2_status.txt 2>&1 || true
	@docker exec $(DB3_CONTAINER) mysql -u root -p"$(DB_ROOT_PASS)" -e "SHOW REPLICA STATUS\G" > $(EVIDENCE_BASE_DIR)/database/replica3_status.txt 2>&1 || true
	@docker exec $(APP1_CONTAINER) php artisan migrate:status > $(EVIDENCE_BASE_DIR)/database/migrations_status.txt 2>&1 || true
	@echo -e "  [$(CLR_GREEN)OK$(CLR_RESET)] Database artifacts stored in $(EVIDENCE_BASE_DIR)/database"

# Security & Regression Evidence Collection
evidence-security:
	@echo -e "$(CLR_BOLD)==> Collecting Security Evidence...$(CLR_RESET)"
	@mkdir -p $(EVIDENCE_BASE_DIR)/security $(EVIDENCE_BASE_DIR)/regression
	@python3 scripts/test/security_and_regression_test.py > $(EVIDENCE_BASE_DIR)/security/attack_suite.txt 2>&1 || true
	@cp -f scripts/test/security_and_regression_results.json $(EVIDENCE_BASE_DIR)/security/ 2>/dev/null || true
	@composer audit --locked > $(EVIDENCE_BASE_DIR)/security/composer_audit.txt 2>&1 || true
	@echo -e "  [$(CLR_GREEN)OK$(CLR_RESET)] Security artifacts stored in $(EVIDENCE_BASE_DIR)/security"

# Performance & Load Test Evidence Collection
evidence-performance:
	@echo -e "$(CLR_BOLD)==> Collecting Performance & Load Test Evidence...$(CLR_RESET)"
	@mkdir -p $(EVIDENCE_BASE_DIR)/performance
	@cp -f scripts/test/*results.json $(EVIDENCE_BASE_DIR)/performance/ 2>/dev/null || true
	@echo -e "  [$(CLR_GREEN)OK$(CLR_RESET)] Performance artifacts stored in $(EVIDENCE_BASE_DIR)/performance"

# Summary Report Generation
report:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Empirical Readiness Verification Summary$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "Target Environment:     $(CLR_CYAN)$(ENV)$(CLR_RESET)"
	@echo -e "Report Timestamp (UTC): $(TIMESTAMP)"
	@echo -e "Git Commit SHA:         $(shell git rev-parse --short HEAD 2>/dev/null || echo 'N/A')"
	@echo -e "Technical Readiness:    $(CLR_YELLOW)PENDING EVIDENCE REMEDIATION$(CLR_RESET)"
	@echo -e "Production Auth:        $(CLR_RED)NOT GRANTED (PENDING AUDIT)$(CLR_RESET)"
	@echo -e "Production IP:          $(PROD_IP) (STRICTLY READ-ONLY - ZERO MUTATIONS)"
	@echo -e "Dossier Location:       $(EVIDENCE_BASE_DIR)/"
