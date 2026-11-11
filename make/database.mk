# ==============================================================================
# NHMP 130 - Database Verification, Replication & Schema Management Module
# ==============================================================================

.PHONY: db-health db-status db-replication-check db-schema-check db-index-check db-backup-verify migrate fresh migrate-legacy bootstrap-db

# Check MySQL primary and replica ping connectivity
db-health:
	@echo -e "$(CLR_BOLD)==> Testing Database Cluster Connectivity ($(ENV))...$(CLR_RESET)"
	@FAIL=0; \
	for db in $(DB1_CONTAINER) $(DB2_CONTAINER) $(DB3_CONTAINER); do \
		echo -n "  -> Checking $$db: "; \
		if docker exec $$db mysqladmin ping -u root -p"$(DB_ROOT_PASS)" --silent 2>/dev/null; then \
			echo -e "[$(CLR_GREEN)ALIVE / PING OK$(CLR_RESET)]"; \
		else \
			echo -e "[$(CLR_RED)UNREACHABLE$(CLR_RESET)]"; \
			FAIL=1; \
		fi; \
	done; \
	if [ $$FAIL -ne 0 ]; then exit 1; fi

# Display primary and replica identities, buffer pool, durability and active connections
db-status:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Database Cluster Architecture & Durability State$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)MySQL Primary ($(DB1_CONTAINER)):$(CLR_RESET)"
	@docker exec $(DB1_CONTAINER) mysql -u root -p"$(DB_ROOT_PASS)" -e "SELECT @@server_id AS server_id, @@gtid_mode AS gtid_mode, @@enforce_gtid_consistency AS gtid_consistency, @@read_only AS read_only;" 2>/dev/null
	@echo -e "Durability Settings:"
	@docker exec $(DB1_CONTAINER) mysql -u root -p"$(DB_ROOT_PASS)" -e "SHOW VARIABLES WHERE Variable_name IN ('innodb_buffer_pool_size', 'innodb_flush_log_at_trx_commit', 'sync_binlog');" 2>/dev/null
	@echo -e "\n$(CLR_BOLD)Active Connections & Threads:$(CLR_RESET)"
	@docker exec $(DB1_CONTAINER) mysql -u root -p"$(DB_ROOT_PASS)" -e "SHOW STATUS WHERE Variable_name IN ('Threads_connected', 'Threads_running', 'Innodb_row_lock_waits', 'Slow_queries');" 2>/dev/null

# Inspect replication lag, IO/SQL running state, and GTID synchronization on all replicas
db-replication-check:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Multi-Replica GTID Replication Status$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@FAIL=0; \
	for replica in $(DB2_CONTAINER) $(DB3_CONTAINER); do \
		echo -e "\n$(CLR_BOLD)Checking Replica $$replica:$(CLR_RESET)"; \
		IO_RUNNING=$$(docker exec $$replica mysql -u root -p"$(DB_ROOT_PASS)" -e "SHOW REPLICA STATUS\G" 2>/dev/null | grep "Replica_IO_Running:" | awk '{print $$2}'); \
		SQL_RUNNING=$$(docker exec $$replica mysql -u root -p"$(DB_ROOT_PASS)" -e "SHOW REPLICA STATUS\G" 2>/dev/null | grep "Replica_SQL_Running:" | awk '{print $$2}'); \
		LAG=$$(docker exec $$replica mysql -u root -p"$(DB_ROOT_PASS)" -e "SHOW REPLICA STATUS\G" 2>/dev/null | grep "Seconds_Behind_Source:" | awk '{print $$2}'); \
		LAST_ERR=$$(docker exec $$replica mysql -u root -p"$(DB_ROOT_PASS)" -e "SHOW REPLICA STATUS\G" 2>/dev/null | grep "Last_SQL_Error:" | cut -d: -f2- | sed 's/^[ \t]*//'); \
		echo "  Source Host:           db-1:3306"; \
		echo "  Replica_IO_Running:    $$IO_RUNNING"; \
		echo "  Replica_SQL_Running:   $$SQL_RUNNING"; \
		echo "  Seconds_Behind_Source: $$LAG seconds"; \
		if [ "$$IO_RUNNING" = "Yes" ] && [ "$$SQL_RUNNING" = "Yes" ] && [ "$$LAG" = "0" ]; then \
			echo -e "  Replication Health:    [$(CLR_GREEN)HEALTHY - IN SYNC$(CLR_RESET)]"; \
		else \
			echo -e "  Replication Health:    [$(CLR_RED)DEGRADED OR LAGGING$(CLR_RESET)]"; \
			if [ -n "$$LAST_ERR" ]; then echo "  Error: $$LAST_ERR"; fi; \
			FAIL=1; \
		fi; \
	done; \
	if [ $$FAIL -ne 0 ]; then exit 1; fi

# Check Laravel migration status
db-schema-check:
	@echo -e "$(CLR_BOLD)==> Verifying Database Schema Migrations ($(ENV))...$(CLR_RESET)"
	@docker exec $(APP1_CONTAINER) php artisan migrate:status

# Verify critical composite performance indexes
db-index-check:
	@echo -e "$(CLR_BOLD)==> Verifying Critical Performance Indexes on calls table...$(CLR_RESET)"
	@docker exec $(DB1_CONTAINER) mysql -u root -p"$(DB_ROOT_PASS)" -e "SHOW INDEX FROM $(DB_NAME).calls WHERE Key_name IN ('calls_caller_history_idx', 'calls_dashboard_perf_idx', 'calls_route_carriageway_idx', 'calls_created_at_idx');" 2>/dev/null

# Verify integrity of baseline database backup archives
db-backup-verify:
	@echo -e "$(CLR_BOLD)==> Verifying Baseline Backup Archive Integrity...$(CLR_RESET)"
	@if ls backups/*.sql.gz 1>/dev/null 2>&1; then \
		for b in backups/*.sql.gz; do \
			echo -n "  -> Checking $$b: "; \
			if gzip -t "$$b" 2>/dev/null; then \
				SHA=$$(sha256sum "$$b" | cut -d' ' -f1); \
				echo -e "[$(CLR_GREEN)VALID GZIP$(CLR_RESET)] SHA-256: $$SHA"; \
			else \
				echo -e "[$(CLR_RED)CORRUPT ARCHIVE$(CLR_RESET)]"; \
				exit 1; \
			fi; \
		done; \
	else \
		echo "  No backups/*.sql.gz found. Checking staging baseline..."; \
	fi

# Run pending migrations (Guarded: NEVER automatic on production)
migrate:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)==> Running Pending Migrations on $(ENV)...$(CLR_RESET)"
	docker exec $(APP1_CONTAINER) php artisan migrate --force

# Destructive: Fresh database wipe and seed (Strictly Guarded)
fresh:
	$(call guard-not-production)
	$(call guard-confirm,Wipe and recreate all database tables with seed data,yes-destroy-database)
	docker exec $(APP1_CONTAINER) php artisan migrate:fresh --seed --force --seeder=ProductionSeeder
	docker exec $(APP1_CONTAINER) php artisan optimize:clear

# Destructive: Legacy schema and data extraction/import (Strictly Guarded)
migrate-legacy:
	$(call guard-not-production)
	$(call guard-confirm,Execute legacy database transformation,yes-legacy-migrate)
	@echo "Executing legacy database transformation..."
	docker exec $(APP1_CONTAINER) php artisan migrate:fresh --force -v
	docker exec $(APP1_CONTAINER) php artisan db:seed --class=ProductionSeeder --force -v

# MySQL Group Replication bootstrap (Strictly Guarded)
bootstrap-db:
	$(call guard-not-production)
	$(call guard-confirm,Initialize MySQL Group Replication cluster,yes-bootstrap-cluster)
	@echo "Bootstrapping replication cluster..."

# ==============================================================================
# Database Snapshot & Local Refresh Automation
# ==============================================================================
.PHONY: refresh-local-db-from-production refresh-local-db-dry-run refresh-local-db-verify refresh-local-db-backup refresh-local-db-restore

# Display proposed refresh workflow without connecting remotely or mutating data
refresh-local-db-dry-run:
	$(guard-target-is-local)
	@bash scripts/refresh_local_db.sh dry-run

# Obtain authorized sanitized production snapshot and refresh local database
refresh-local-db-from-production:
	$(guard-target-is-local)
	@CONFIRM_REFRESH="$(or $(CONFIRM_REFRESH),$(CONFIRM))" bash scripts/refresh_local_db.sh from-production

# Verify restored local database (schema, 45 tables, FKs, sequences, smoke test)
refresh-local-db-verify:
	$(guard-target-is-local)
	@bash scripts/refresh_local_db.sh verify

# Create verified gzip backup of current local development database
refresh-local-db-backup:
	$(guard-target-is-local)
	@bash scripts/refresh_local_db.sh backup

# Restore a previously downloaded and verified local snapshot
refresh-local-db-restore:
	$(guard-target-is-local)
	@bash scripts/refresh_local_db.sh restore $(SNAPSHOT)
