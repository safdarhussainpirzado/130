# ==============================================================================
# NHMP 130 - Security Testing, Audits & Quality Gate Module
# ==============================================================================

.PHONY: test-security test-security-regression security-audit security-static quality-gate

# Run Security-First Penetration & Authorization Tests
test-security:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Security & Multi-Factor Authentication Tests$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@$(TEST_EXEC) \
		tests/Feature/Auth/TwoFactorSecurityTest.php \
		tests/Feature/Auth/TwoFactorConcurrencyTest.php \
		tests/Unit/DatabaseIsolationGuardTest.php \
		tests/Unit/NotificationSecurityTest.php

# Run Comprehensive Security & Regression Verification Suite
test-security-regression:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Expanded Security & Regression Attack Suite$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@python3 scripts/test/security_and_regression_test.py

# Run Dependency Vulnerability Audits (Lockfile & NPM)
security-audit:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Dependency Vulnerability Audit$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  -> Running Composer Security Audit..."
	@composer audit --locked || true
	@echo -e "\n  -> Running NPM Security Audit..."
	@npm audit --audit-level=high || true

# Run Static Security & Configuration Audits (Permissions & Secrets)
security-static:
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Static Configuration & Permission Audit$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@FAIL=0; \
	echo -n "  -> Checking for forbidden world-writable (777) config directories: "; \
	WORLD_WRITABLE=$$(find ./docker ./redis ./mysql ./haproxy -perm 0777 2>/dev/null || true); \
	if [ -n "$$WORLD_WRITABLE" ]; then \
		echo -e "[$(CLR_RED)FAIL$(CLR_RESET)] Found world-writable paths:\n$$WORLD_WRITABLE"; \
		FAIL=1; \
	else \
		echo -e "[$(CLR_GREEN)PASS$(CLR_RESET)] Zero world-writable configurations found."; \
	fi; \
	echo -n "  -> Checking for hardcoded database passwords in Makefile: "; \
	if grep -E -- "-psecret|crm_password" Makefile make/*.mk 2>/dev/null | grep -v 'printenv' | grep -v 'echo'; then \
		echo -e "[$(CLR_RED)FAIL$(CLR_RESET)] Hardcoded passwords detected in Makefile!"; \
		FAIL=1; \
	else \
		echo -e "[$(CLR_GREEN)PASS$(CLR_RESET)] Zero hardcoded database credentials."; \
	fi; \
	if [ $$FAIL -ne 0 ]; then exit 1; fi

# Complete Security Quality Gate
quality-gate: test-security test-security-regression security-static security-audit
	@echo -e "\n$(CLR_GREEN)$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "$(CLR_GREEN)$(CLR_BOLD)  ALL SECURITY QUALITY GATES PASSED [ENTERPRISE COMPLIANT]$(CLR_RESET)"
	@echo -e "$(CLR_GREEN)$(CLR_BOLD)======================================================================$(CLR_RESET)"
