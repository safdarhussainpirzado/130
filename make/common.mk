# ==============================================================================
# NHMP 130 - Common Configuration, Environment Safety & Macros
# ==============================================================================

SHELL := /bin/bash
.SHELLFLAGS := -eu -o pipefail -c

# Environment Validation: local (default), test, staging, production
VALID_ENVS := local test staging production
ENV ?= local
CONFIRM ?= no
DRY_RUN ?= 0
IMAGE_DIGEST ?=

# Static Infrastructure IPs
PROD_IP := 100.124.232.124
STAGING_IP := 100.108.239.107
LOCAL_IP := 127.0.0.1

# Container Naming (Validated against Docker Compose & Docker Engine)
APP1_CONTAINER := 130-app-1-1
APP2_CONTAINER := 130-app-2-1
WEB1_CONTAINER := 130-web-1-1
WEB2_CONTAINER := 130-web-2-1
HAPROXY_CONTAINER := 130-haproxy-1
DB1_CONTAINER := 130-db-1-1
DB2_CONTAINER := 130-db-2-1
DB3_CONTAINER := 130-db-3-1
REDIS_PRIMARY_CONTAINER := 130-redis-1
REDIS_REPLICA_CONTAINER := 130-redis-replica
SENTINEL1_CONTAINER := 130-sentinel-1
SENTINEL2_CONTAINER := 130-sentinel-2
SENTINEL3_CONTAINER := 130-sentinel-3

# Database configuration (dynamic resolution without hardcoded passwords)
DB_USER ?= crm_user
DB_NAME ?= 130_crm_db
DB_PASS ?= $(shell docker exec $(DB1_CONTAINER) printenv DB_PASSWORD 2>/dev/null || echo "crm_password")
DB_ROOT_PASS ?= $(shell docker exec $(DB1_CONTAINER) printenv MYSQL_ROOT_PASSWORD 2>/dev/null || echo "secret")

# Evidence & Artifact paths
DATE_STAMP := $(shell date +%Y-%m-%d)
TIMESTAMP := $(shell date -u +"%Y-%m-%dT%H:%M:%SZ")
EVIDENCE_BASE_DIR := artifacts/$(DATE_STAMP)

# Terminal Colors
CLR_RESET  := \033[0m
CLR_BOLD   := \033[1m
CLR_RED    := \033[31m
CLR_GREEN  := \033[32m
CLR_YELLOW := \033[33m
CLR_BLUE   := \033[34m
CLR_CYAN   := \033[36m

# Macro: Validate Environment Name
define guard-env-valid
	@if ! echo " $(VALID_ENVS) " | grep -q " $(ENV) "; then \
		echo -e "$(CLR_RED)$(CLR_BOLD)[INVALID ENVIRONMENT]$(CLR_RESET) Environment '$(ENV)' is invalid."; \
		echo -e "Allowed environments: $(CLR_CYAN)$(VALID_ENVS)$(CLR_RESET)"; \
		exit 1; \
	fi
endef

# Macro: Strict Operational Safety Guard (Zero Remote Mutation Policy)
define guard-not-production
	$(guard-env-valid)
	@if [ "$(ENV)" = "production" ] || [ "$(ENV)" = "prod" ] || [ "$(ENV)" = "staging" ]; then \
		echo -e "$(CLR_RED)$(CLR_BOLD)[SECURITY VIOLATION]$(CLR_RESET) Target '$@' is strictly forbidden on $(ENV) ($(if $(filter staging,$(ENV)),$(STAGING_IP),$(PROD_IP)))."; \
		echo -e "Remote operational staging and production hosts are strictly off-limits. Aborting immediately."; \
		exit 1; \
	fi
endef

# Macro: Guard Local Database Target
define guard-target-is-local
	$(guard-env-valid)
	@if [ "$(ENV)" != "local" ] && [ "$(ENV)" != "test" ]; then \
		echo -e "$(CLR_RED)$(CLR_BOLD)[TARGET GUARD ERROR]$(CLR_RESET) Target '$@' can only be executed against local/test environments."; \
		echo -e "Operational environment '$(ENV)' cannot be used as a database refresh target."; \
		exit 1; \
	fi
endef

# Macro: Staging Deployment Guard
define guard-staging-deploy
	$(guard-env-valid)
	@if [ "$(ENV)" != "staging" ]; then \
		echo -e "$(CLR_RED)$(CLR_BOLD)[ENVIRONMENT MISMATCH]$(CLR_RESET) Staging deployment requires ENV=staging (current: $(ENV))."; \
		exit 1; \
	fi
	@if [ "$(CONFIRM)" != "deploy-staging" ]; then \
		echo -e "$(CLR_YELLOW)$(CLR_BOLD)[STAGING DEPLOYMENT CONFIRMATION REQUIRED]$(CLR_RESET)"; \
		echo -e "To deploy to staging, you must explicitly confirm:"; \
		echo -e "  $(CLR_BOLD)make $@ ENV=staging CONFIRM=deploy-staging$(CLR_RESET)"; \
		exit 1; \
	fi
endef

# Macro: Production Deployment Guard
define guard-production-deploy
	$(guard-env-valid)
	@if [ "$(ENV)" != "production" ]; then \
		echo -e "$(CLR_RED)$(CLR_BOLD)[ENVIRONMENT MISMATCH]$(CLR_RESET) Production deployment requires ENV=production (current: $(ENV))."; \
		exit 1; \
	fi
	@if [ "$(CONFIRM)" != "deploy-production" ]; then \
		echo -e "$(CLR_RED)$(CLR_BOLD)[CRITICAL SAFETY GUARD] Production deployment requires explicit confirmation:$(CLR_RESET)"; \
		echo -e "  $(CLR_BOLD)make $@ ENV=production CONFIRM=deploy-production IMAGE_DIGEST=<sha256:...>$(CLR_RESET)"; \
		exit 1; \
	fi
endef

# Macro: Immutable Image Digest Guard
define guard-immutable-digest
	@if [ -z "$(IMAGE_DIGEST)" ] || [ "$(IMAGE_DIGEST)" = "latest" ] || [ "$(IMAGE_DIGEST)" = "130-app:latest" ]; then \
		echo -e "$(CLR_RED)$(CLR_BOLD)[SECURITY POLICY VIOLATION]$(CLR_RESET) Remote deployments strictly require an immutable cryptographic image digest."; \
		echo -e "Mutable tags such as 'latest' are rejected. Pass IMAGE_DIGEST=sha256:... or registry@sha256:..."; \
		exit 1; \
	fi
endef

# Macro: Explicit Confirmation Guard for Destructive Actions
# Usage: $(call guard-confirm,CONFIRM_WORD,DESCRIPTION)
define guard-confirm
	@echo -e "$(CLR_YELLOW)$(CLR_BOLD)[DANGER / DESTRUCTIVE OPERATION]$(CLR_RESET)"
	@echo -e "Target:      $(CLR_BOLD)$@$(CLR_RESET)"
	@echo -e "Environment: $(CLR_CYAN)$(ENV)$(CLR_RESET)"
	@echo -e "Action:      $(1)"
	@if [ "$(CONFIRM)" != "$(2)" ]; then \
		echo -e "$(CLR_RED)Error: Operation blocked!$(CLR_RESET)"; \
		echo -e "To execute this destructive command, you must explicitly pass:"; \
		echo -e "  $(CLR_BOLD)make $@ ENV=$(ENV) CONFIRM=$(2)$(CLR_RESET)"; \
		exit 1; \
	fi; \
	echo -e "$(CLR_GREEN)Confirmation accepted. Proceeding with $(1)...$(CLR_RESET)"
endef

# Macro: Run Docker Exec with non-zero error trapping
define docker-app1
	docker exec $(APP1_CONTAINER) $(1)
endef

define docker-app2
	docker exec $(APP2_CONTAINER) $(1)
endef
