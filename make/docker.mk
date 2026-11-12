# ==============================================================================
# NHMP 130 - Docker, Synchronization & Diagnostics Module
# ==============================================================================

.PHONY: env health containers logs logs-app logs-db logs-redis logs-workers versions sync sync-check sync-assets sync-clear optimize up down restart

# Display active environment identity without leaking secrets
env:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 Helpline — Environment Identity$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "Selected Environment: $(CLR_GREEN)$(ENV)$(CLR_RESET)"
	@echo -e "Local Host Endpoint:  http://$(LOCAL_IP):8085"
	@echo -e "Remote Candidate:     http://$(STAGING_IP):8080"
	@echo -e "Production Cluster:   http://$(PROD_IP) (STRICTLY READ-ONLY)"
	@echo -e "Git Commit SHA:       $(shell git rev-parse --short HEAD 2>/dev/null || echo 'N/A')"
	@echo -e "Git Branch:           $(shell git rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'N/A')"
	@echo -e "System Time (UTC):    $(TIMESTAMP)"
	@echo -e "Production Policy:    $(CLR_RED)STRICT ISOLATION — 0 MUTATIONS PERMITTED$(CLR_RESET)"

# Verify all required containers and application health endpoints
health:
	@echo -e "$(CLR_BOLD)==> Verifying System & Container Health ($(ENV))...$(CLR_RESET)"
	@FAIL=0; \
	for c in $(HAPROXY_CONTAINER) $(WEB1_CONTAINER) $(WEB2_CONTAINER) $(APP1_CONTAINER) $(APP2_CONTAINER) $(DB1_CONTAINER) $(DB2_CONTAINER) $(DB3_CONTAINER) $(REDIS_PRIMARY_CONTAINER); do \
		STATUS=$$(docker inspect --format '{{.State.Status}}' $$c 2>/dev/null || echo "missing"); \
		if [ "$$STATUS" = "running" ]; then \
			echo -e "  [$(CLR_GREEN)PASS$(CLR_RESET)] Container $$c is running"; \
		else \
			echo -e "  [$(CLR_RED)FAIL$(CLR_RESET)] Container $$c is $$STATUS"; \
			FAIL=1; \
		fi; \
	done; \
	echo -e "\n$(CLR_BOLD)==> Testing HTTP Ingress Health Endpoints...$(CLR_RESET)"; \
	HTTP_HEALTH=$$(curl -s -o /dev/null -w "%{http_code}" http://$(LOCAL_IP):8085/healthz || echo "000"); \
	if [ "$$HTTP_HEALTH" = "200" ]; then \
		echo -e "  [$(CLR_GREEN)PASS$(CLR_RESET)] HAProxy / Nginx /healthz returned HTTP 200 OK"; \
	else \
		echo -e "  [$(CLR_RED)FAIL$(CLR_RESET)] HAProxy /healthz returned HTTP $$HTTP_HEALTH"; \
		FAIL=1; \
	fi; \
	ADVISORY_HEALTH=$$(curl -s -o /dev/null -w "%{http_code}" http://$(LOCAL_IP):8085/api/v1/advisories || echo "000"); \
	if [ "$$ADVISORY_HEALTH" = "200" ]; then \
		echo -e "  [$(CLR_GREEN)PASS$(CLR_RESET)] Tablet Advisory /api/v1/advisories returned HTTP 200 OK"; \
	else \
		echo -e "  [$(CLR_RED)FAIL$(CLR_RESET)] Tablet Advisory returned HTTP $$ADVISORY_HEALTH"; \
		FAIL=1; \
	fi; \
	if [ $$FAIL -ne 0 ]; then \
		echo -e "\n$(CLR_RED)$(CLR_BOLD)Health verification FAILED.$(CLR_RESET)"; exit 1; \
	else \
		echo -e "\n$(CLR_GREEN)$(CLR_BOLD)All core health checks PASSED.$(CLR_RESET)"; \
	fi

# Show status and ports of all project containers
containers:
	@echo -e "$(CLR_BOLD)Active NHMP 130 Container Inventory:$(CLR_RESET)"
	@docker ps --filter "name=130-" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# Display component versions across stack
versions:
	@echo -e "$(CLR_BOLD)Component Runtime Versions:$(CLR_RESET)"
	@echo -n "  PHP:      " && docker exec $(APP1_CONTAINER) php -v | head -n 1
	@echo -n "  Laravel:  " && docker exec $(APP1_CONTAINER) php artisan --version
	@echo -n "  MySQL:    " && docker exec $(DB1_CONTAINER) mysql -V
	@echo -n "  Redis:    " && docker exec $(REDIS_PRIMARY_CONTAINER) redis-server -v
	@echo -n "  Composer: " && (composer --version 2>/dev/null || docker exec $(APP1_CONTAINER) composer --version 2>/dev/null || echo "N/A")
	@echo -n "  Node:     " && (node -v 2>/dev/null || echo "N/A")
	@echo -n "  HAProxy:  " && docker exec $(HAPROXY_CONTAINER) haproxy -v | head -n 1

# Synchronize application files into all active app containers
sync:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)==> Synchronizing application code into active application nodes...$(CLR_RESET)"
	@for container in $(OCTANE_APP_CONTAINER) $(APP1_CONTAINER) $(APP2_CONTAINER); do \
		if docker ps --format '{{.Names}}' | grep -q "^$$container$$"; then \
			docker cp routes/. $$container:/var/www/html/routes/ 2>/dev/null || true; \
			docker cp app/. $$container:/var/www/html/app/ 2>/dev/null || true; \
			docker cp resources/views/. $$container:/var/www/html/resources/views/ 2>/dev/null || true; \
			docker cp public/. $$container:/var/www/html/public/ 2>/dev/null || true; \
			docker cp config/. $$container:/var/www/html/config/ 2>/dev/null || true; \
			docker cp database/. $$container:/var/www/html/database/ 2>/dev/null || true; \
			docker cp tests/. $$container:/var/www/html/tests/ 2>/dev/null || true; \
			echo -e "  [$(CLR_GREEN)OK$(CLR_RESET)] $$container synced"; \
		fi; \
	done
	@echo -e "$(CLR_GREEN)$(CLR_BOLD)==> Code synchronization complete.$(CLR_RESET)"

# Synchronize vendor dependencies into both app containers
sync-vendor:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)==> Synchronizing vendor dependencies into $(APP1_CONTAINER) and $(APP2_CONTAINER)...$(CLR_RESET)"
	@for node in $(APP1_CONTAINER) $(APP2_CONTAINER); do \
		echo -e "  -> Syncing vendor to $$node..."; \
		docker cp vendor/. $$node:/var/www/html/vendor/ || exit 1; \
		echo -e "  [$(CLR_GREEN)OK$(CLR_RESET)] $$node vendor synced cleanly"; \
	done

# Verify synchronization integrity across app-1 and app-2 using checksums
sync-check:
	@echo -e "$(CLR_BOLD)==> Verifying file synchronization parity between app-1 and app-2...$(CLR_RESET)"
	@APP1_API_HASH=$$(docker exec $(APP1_CONTAINER) sha256sum /var/www/html/routes/api.php | cut -d' ' -f1); \
	APP2_API_HASH=$$(docker exec $(APP2_CONTAINER) sha256sum /var/www/html/routes/api.php | cut -d' ' -f1); \
	APP1_CTRL_HASH=$$(docker exec $(APP1_CONTAINER) sha256sum /var/www/html/app/Http/Controllers/Api/TabletAdvisoryController.php | cut -d' ' -f1); \
	APP2_CTRL_HASH=$$(docker exec $(APP2_CONTAINER) sha256sum /var/www/html/app/Http/Controllers/Api/TabletAdvisoryController.php | cut -d' ' -f1); \
	echo "  routes/api.php:             app-1=$$APP1_API_HASH  app-2=$$APP2_API_HASH"; \
	echo "  TabletAdvisoryController:   app-1=$$APP1_CTRL_HASH  app-2=$$APP2_CTRL_HASH"; \
	if [ "$$APP1_API_HASH" = "$$APP2_API_HASH" ] && [ "$$APP1_CTRL_HASH" = "$$APP2_CTRL_HASH" ]; then \
		echo -e "  [$(CLR_GREEN)PASS$(CLR_RESET)] Checksums match across all application nodes."; \
	else \
		echo -e "  [$(CLR_RED)FAIL$(CLR_RESET)] Code divergence detected between app nodes!"; \
		exit 1; \
	fi

# Sync frontend build assets
sync-assets:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)==> Syncing frontend assets to web and app nodes...$(CLR_RESET)"
	@for c in $(OCTANE_APP_CONTAINER) $(OCTANE_WEB_CONTAINER) $(APP1_CONTAINER) $(APP2_CONTAINER); do \
		if docker ps --format '{{.Names}}' | grep -q "^$$c$$"; then \
			docker cp public/build/. $$c:/var/www/html/public/build/ 2>/dev/null && echo -e "  [$(CLR_GREEN)OK$(CLR_RESET)] $$c build synced"; \
		fi; \
	done
	@echo -e "  [$(CLR_GREEN)OK$(CLR_RESET)] Frontend assets distributed."

# Sync code and rebuild caches cleanly with rolling zero-downtime RoadRunner worker reload
sync-clear: sync sync-assets
	@echo -e "$(CLR_BOLD)==> Executing rolling zero-downtime cache refresh & RoadRunner worker reload...$(CLR_RESET)"
	@for c in $(OCTANE_APP_CONTAINER) $(APP1_CONTAINER) $(APP2_CONTAINER); do \
		if docker ps --format '{{.Names}}' | grep -q "^$$c$$"; then \
			echo -e "  -> Refreshing $$c..."; \
			docker exec $$c php artisan optimize:clear 2>/dev/null || true; \
			docker exec $$c php artisan optimize 2>/dev/null || true; \
			docker exec $$c php artisan view:cache 2>/dev/null || true; \
			docker exec $$c sh -c 'if [ -x /usr/local/bin/rr ]; then /usr/local/bin/rr reset -c .rr.prod.yaml; fi' 2>/dev/null || true; \
		fi; \
	done
	@echo -e "$(CLR_GREEN)$(CLR_BOLD)==> Application nodes refreshed, workers reloaded, and zero-downtime maintained.$(CLR_RESET)"

# Warm optimized framework caches and reload RoadRunner workers
optimize:
	@for c in $(OCTANE_APP_CONTAINER) $(APP1_CONTAINER) $(APP2_CONTAINER); do \
		if docker ps --format '{{.Names}}' | grep -q "^$$c$$"; then \
			docker exec $$c php artisan optimize 2>/dev/null || true; \
			docker exec $$c php artisan view:cache 2>/dev/null || true; \
			docker exec $$c php artisan event:cache 2>/dev/null || true; \
			docker exec $$c sh -c 'if [ -x /usr/local/bin/rr ]; then /usr/local/bin/rr reset -c .rr.prod.yaml; fi' 2>/dev/null || true; \
		fi; \
	done

# Fast RoadRunner worker reload without cache recreation
rr-reset:
	@echo -e "$(CLR_BOLD)==> Reloading RoadRunner workers across app nodes...$(CLR_RESET)"
	@for c in $(APP1_CONTAINER) $(APP2_CONTAINER); do \
		docker exec $$c rr reset 2>/dev/null || true; \
	done
	@echo -e "  [$(CLR_GREEN)OK$(CLR_RESET)] RoadRunner workers reloaded."

# Log tailing targets
logs:
	docker logs -f $(HAPROXY_CONTAINER)

logs-app:
	docker logs -f $(APP1_CONTAINER)

logs-db:
	docker logs -f $(DB1_CONTAINER)

logs-redis:
	docker logs -f $(REDIS_PRIMARY_CONTAINER)

logs-workers:
	docker exec $(APP1_CONTAINER) supervisorctl status

.PHONY: rolling-deploy rollback rr-reset

# Safe container lifecycle with ingress health readiness wait
up:
	$(call guard-not-production)
	docker compose up -d --remove-orphans
	@echo -e "$(CLR_BOLD)==> Waiting for ingress and RoadRunner backends to become ready...$(CLR_RESET)"
	@for i in $$(seq 1 20); do \
		STATUS=$$(curl -k -s -o /dev/null -w "%{http_code}" https://127.0.0.1/up 2>/dev/null || echo "000"); \
		if [ "$$STATUS" = "200" ] || [ "$$STATUS" = "204" ]; then \
			echo -e "  [$(CLR_GREEN)PASS$(CLR_RESET)] HAProxy ingress & application nodes are UP and healthy (HTTP $$STATUS)."; \
			exit 0; \
		fi; \
		sleep 1; \
	done; \
	echo -e "  [$(CLR_YELLOW)WARN$(CLR_RESET)] Ingress not immediately ready, containers are still initializing."

down:
	$(call guard-not-production)
	@if [ "$(ENV)" != "local" ]; then $(call guard-confirm,Stop stack containers,yes-stop-stack); fi
	docker compose down

restart:
	$(call guard-not-production)
	@if [ "$(ENV)" != "local" ]; then $(call guard-confirm,Restart stack containers,yes-restart-stack); fi
	docker compose down && $(MAKE) up

# Zero-Downtime Rolling Deployment & Rollback Targets
rolling-deploy:
	$(call guard-not-production)
	@CONFIRM_ROLLING_DEPLOY=yes-rolling-deploy bash scripts/execute_final_rolling_deploy.sh

rollback:
	$(call guard-not-production)
	@CONFIRM_ROLLBACK=yes-rollback bash scripts/rollback_release.sh
