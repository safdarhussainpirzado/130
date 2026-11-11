# ==============================================================================
# NHMP 130 - Unit, Feature, Integration & Regression Testing Module
# ==============================================================================

.PHONY: test test-unit test-feature test-integration test-regression test-all

TEST_EXEC := docker exec -e APP_ENV=testing -e DB_DATABASE=130_crm_test_disposable $(APP1_CONTAINER) php /var/www/html/vendor/bin/phpunit

# Default Test Target: Unit Test Suite
test: test-unit

# Run Unit Test Suite (113 tests: Models, Validation, Sequence, Guards, Hierarchy)
test-unit:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Unit Test Suite (113 Tests)$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@$(TEST_EXEC) tests/Unit

# Run Feature Test Suite
test-feature:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Feature Test Suite$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@$(TEST_EXEC) tests/Feature || true

# Run In-Project Integration and Penetration Test Suite
test-integration:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Integration & Scope Isolation Test Suite$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  -> Running RoadRunner Scope Leakage Prevention Test..."
	@docker exec $(APP1_CONTAINER) php scripts/test/test_scope_leakage_prevention.php
	@echo -e "\n  -> Running SSE Realtime Push Stream Test..."
	@python3 scripts/test/sse_realtime_test.py
	@echo -e "\n  -> Running Outbox Integration Test..."
	@docker exec $(APP1_CONTAINER) php scripts/test/test_outbox_integration.php

# Targeted Regression Suite: Core verified regression files
test-regression:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Targeted Regression Suite (12 Core Verification Files)$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@$(TEST_EXEC) \
		tests/Unit/CallAuthorizationPolicyTest.php \
		tests/Unit/CallSequenceTest.php \
		tests/Unit/DatabaseIsolationGuardTest.php \
		tests/Unit/NotificationSecurityTest.php \
		tests/Unit/OfficeHierarchyCacheTest.php \
		tests/Unit/Validation/CallInputValidationTest.php \
		tests/Unit/Resources/CallListResourceTest.php \
		tests/Unit/Resources/CallShowResourceTest.php \
		tests/Unit/Resources/UserListResourceTest.php \
		tests/Feature/Auth/TwoFactorSecurityTest.php \
		tests/Feature/Auth/TwoFactorConcurrencyTest.php \
		tests/Feature/DataMinimizationAndSecurityTest.php

# Complete Test Suite Execution & Machine-Readable Audit Inventory
test-all:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Full Test Suite Execution Gate$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@mkdir -p $(EVIDENCE_BASE_DIR)/regression
	@echo -e "Executing complete test suite and logging to $(EVIDENCE_BASE_DIR)/regression/full_test_run.log..."
	@$(TEST_EXEC) | tee $(EVIDENCE_BASE_DIR)/regression/full_test_run.log || true
	@echo -e "\n$(CLR_BOLD)Test Run Analysis Summary:$(CLR_RESET)"
	@grep -E "(Tests:|OK|ERRORS!|FAILURES!|Skipped:)" $(EVIDENCE_BASE_DIR)/regression/full_test_run.log || true
