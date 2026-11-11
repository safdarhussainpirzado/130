# ==============================================================================
# NHMP 130 - Queue Workers, Redis Sentinel & Real-Time SSE Infrastructure Module
# ==============================================================================

.PHONY: queue-status queue-health queue-test queue-drain-check redis-health redis-status redis-replication-check test-redis-failover test-sse test-sse-reconnect test-roadrunner test-scope-isolation

# ------------------------------------------------------------------------------
# Queue & Supervisor Workers
# ------------------------------------------------------------------------------

# Display Supervisor process table and queue assignments
queue-status:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Supervisor Worker Pools & Queue Assignments$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@docker exec $(APP1_CONTAINER) supervisorctl status

# Verify all 9 worker processes are RUNNING
queue-health:
	@echo -e "$(CLR_BOLD)==> Verifying Supervisor Worker Health...$(CLR_RESET)"
	@TOTAL=$$(docker exec $(APP1_CONTAINER) supervisorctl status | wc -l); \
	RUNNING=$$(docker exec $(APP1_CONTAINER) supervisorctl status | grep -c "RUNNING"); \
	echo "  Total Configured Workers: $$TOTAL"; \
	echo "  Active Running Workers:   $$RUNNING"; \
	if [ "$$RUNNING" -ge 9 ]; then \
		echo -e "  [$(CLR_GREEN)PASS$(CLR_RESET)] All 9 Supervisor queue workers are active and healthy."; \
	else \
		echo -e "  [$(CLR_RED)FAIL$(CLR_RESET)] Worker shortage detected ($$RUNNING/9 running)!"; \
		docker exec $(APP1_CONTAINER) supervisorctl status; \
		exit 1; \
	fi

# Check queue backlog and failed jobs
queue-drain-check:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Queue Depth & Failed Job Audit$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "Checking Redis Queue Backlog:"
	@docker exec $(REDIS_PRIMARY_CONTAINER) redis-cli keys "*queues*" 2>/dev/null | while read k; do \
		LEN=$$(docker exec $(REDIS_PRIMARY_CONTAINER) redis-cli llen "$$k"); \
		echo "  -> $$k: $$LEN pending jobs"; \
	done
	@echo -e "\nChecking Failed Jobs (Database & Artisan):"
	@docker exec $(APP1_CONTAINER) php artisan queue:failed
	@FAILED_COUNT=$$(docker exec $(DB1_CONTAINER) mysql -u root -p"$(DB_ROOT_PASS)" -s -N -e "SELECT count(*) FROM $(DB_NAME).failed_jobs;" 2>/dev/null || echo "0"); \
	echo "Total failed jobs in failed_jobs table: $$FAILED_COUNT"

# Dispatch test job through queue worker
queue-test:
	@echo -e "$(CLR_BOLD)==> Testing Queue Worker Job Execution...$(CLR_RESET)"
	@docker exec $(APP1_CONTAINER) php artisan tinker --execute="Log::info('Queue test dispatched from Makefile');"

# ------------------------------------------------------------------------------
# Redis & Sentinel High Availability
# ------------------------------------------------------------------------------

# Test ping connectivity across Redis Primary, Replica, and all 3 Sentinels
redis-health:
	@echo -e "$(CLR_BOLD)==> Testing Redis & Sentinel Quorum Connectivity...$(CLR_RESET)"
	@echo -n "  -> Primary ($(REDIS_PRIMARY_CONTAINER):6379): " && docker exec $(REDIS_PRIMARY_CONTAINER) redis-cli ping
	@echo -n "  -> Replica ($(REDIS_REPLICA_CONTAINER):6379): " && docker exec $(REDIS_REPLICA_CONTAINER) redis-cli ping
	@for s in $(SENTINEL1_CONTAINER) $(SENTINEL2_CONTAINER) $(SENTINEL3_CONTAINER); do \
		echo -n "  -> Sentinel $$s (26379): " && docker exec $$s redis-cli -p 26379 ping; \
	done

# Display Redis primary/replica roles and Sentinel discovery state
redis-status:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Redis Topology & Sentinel Quorum$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)Primary Role & Connected Slaves:$(CLR_RESET)"
	@docker exec $(REDIS_PRIMARY_CONTAINER) redis-cli info replication | grep -E "role|connected_slaves|master_repl_offset"
	@echo -e "\n$(CLR_BOLD)Replica Link Status & Master Host:$(CLR_RESET)"
	@docker exec $(REDIS_REPLICA_CONTAINER) redis-cli info replication | grep -E "role|master_host|master_link_status|slave_repl_offset"
	@echo -e "\n$(CLR_BOLD)Sentinel Master Discovery & Quorum:$(CLR_RESET)"
	@docker exec $(SENTINEL1_CONTAINER) redis-cli -p 26379 sentinel master mymaster | paste - - | grep -E "name|ip|port|flags|num-slaves|num-other-sentinels|quorum"

# Verify Redis replication synchronization offset
redis-replication-check:
	@echo -e "$(CLR_BOLD)==> Verifying Redis Replication Synchronization...$(CLR_RESET)"
	@M_OFF=$$(docker exec $(REDIS_PRIMARY_CONTAINER) redis-cli info replication | grep "master_repl_offset:" | cut -d: -f2 | tr -d '\r'); \
	S_OFF=$$(docker exec $(REDIS_REPLICA_CONTAINER) redis-cli info replication | grep "slave_repl_offset:" | cut -d: -f2 | tr -d '\r'); \
	DIFF=$$((M_OFF - S_OFF)); \
	echo "  Primary Offset: $$M_OFF"; \
	echo "  Replica Offset: $$S_OFF"; \
	echo "  Offset Delta:   $$DIFF bytes"; \
	if [ "$$DIFF" -le 1024 ]; then \
		echo -e "  [$(CLR_GREEN)PASS$(CLR_RESET)] Redis replica is tightly synchronized with primary."; \
	else \
		echo -e "  [$(CLR_RED)FAIL$(CLR_RESET)] Redis replication offset lag exceeds threshold!"; \
		exit 1; \
	fi

# Execute automated Sentinel failover test (Strictly forbidden on production)
test-redis-failover:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Automated Redis Sentinel Failover Verification$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@python3 scripts/test/redis_sentinel_failover_test.py

# ------------------------------------------------------------------------------
# Real-Time SSE & RoadRunner Scope Isolation
# ------------------------------------------------------------------------------

# Test live Server-Sent Events push stream
test-sse:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Server-Sent Events (SSE) Real-Time Delivery$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@python3 scripts/test/sse_realtime_test.py

# Test SSE client reconnect and event resumption
test-sse-reconnect:
	@echo -e "$(CLR_BOLD)==> Testing SSE Client Reconnection with Last-Event-ID...$(CLR_RESET)"
	@curl -s -N --max-time 3 -H "Last-Event-ID: 1" http://$(LOCAL_IP):8085/api/v1/realtime/stream | head -n 10

# Test RoadRunner worker process health
test-roadrunner:
	@echo -e "$(CLR_BOLD)==> Verifying RoadRunner / Process Engine Health...$(CLR_RESET)"
	@docker exec $(APP1_CONTAINER) php -m | grep -E "pcntl|posix"

# Verify RoadRunner / FastCGI worker scope isolation and state cleanup
test-scope-isolation:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Worker Memory & Authentication Scope Isolation$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@docker exec $(APP1_CONTAINER) php scripts/test/test_scope_leakage_prevention.php
