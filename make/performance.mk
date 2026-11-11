# ==============================================================================
# NHMP 130 - Performance, Load Testing & Soak Testing Module
# ==============================================================================

.PHONY: test-performance test-load test-load-100 test-load-250 test-load-500 test-load-1000 test-load-2500 test-load-5000 test-load-10000 test-crm-stress test-combined test-soak

K6_IMAGE := grafana/k6:latest
LOAD_DURATION ?= 20s
SOAK_DURATION ?= 7200s

# Run Baseline Performance Suite
test-performance: test-load-100

# Run Full Tiered HTTP Load Test Matrix (100 -> 2,500)
test-load:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Automated Tiered HTTP Concurrency Benchmark$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@python3 scripts/test/run_enterprise_load_matrix.py

# Individual Concurrency Tiers
test-load-100:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)==> Running Concurrency Tier: 100 VUs (Duration: $(LOAD_DURATION))...$(CLR_RESET)"
	@cat scripts/test/k6_workload.js | docker run --rm -i --net=host $(K6_IMAGE) run -e VUS=100 -e DURATION=$(LOAD_DURATION) -

test-load-250:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)==> Running Concurrency Tier: 250 VUs (Duration: $(LOAD_DURATION))...$(CLR_RESET)"
	@cat scripts/test/k6_workload.js | docker run --rm -i --net=host $(K6_IMAGE) run -e VUS=250 -e DURATION=$(LOAD_DURATION) -

test-load-500:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)==> Running Concurrency Tier: 500 VUs (Duration: $(LOAD_DURATION))...$(CLR_RESET)"
	@cat scripts/test/k6_workload.js | docker run --rm -i --net=host $(K6_IMAGE) run -e VUS=500 -e DURATION=$(LOAD_DURATION) -

test-load-1000:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)==> Running Concurrency Tier: 1,000 VUs (Duration: $(LOAD_DURATION))...$(CLR_RESET)"
	@cat scripts/test/k6_workload.js | docker run --rm -i --net=host $(K6_IMAGE) run -e VUS=1000 -e DURATION=$(LOAD_DURATION) -

test-load-2500:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)==> Running Concurrency Tier: 2,500 VUs (Duration: $(LOAD_DURATION))...$(CLR_RESET)"
	@cat scripts/test/k6_workload.js | docker run --rm -i --net=host $(K6_IMAGE) run -e VUS=2500 -e DURATION=$(LOAD_DURATION) -

test-load-5000:
	$(call guard-not-production)
	$(call guard-confirm,Execute extreme 5000 VU stress test,yes-load-5000)
	@echo -e "$(CLR_BOLD)==> Running Concurrency Tier: 5,000 VUs (Duration: $(LOAD_DURATION))...$(CLR_RESET)"
	@cat scripts/test/k6_workload.js | docker run --rm -i --net=host $(K6_IMAGE) run -e VUS=5000 -e DURATION=$(LOAD_DURATION) -

test-load-10000:
	$(call guard-not-production)
	$(call guard-confirm,Execute extreme 10000 VU stress test (Only after lower tiers pass!),yes-load-10000)
	@echo -e "$(CLR_BOLD)==> Running Concurrency Tier: 10,000 VUs (Duration: $(LOAD_DURATION))...$(CLR_RESET)"
	@cat scripts/test/k6_workload.js | docker run --rm -i --net=host $(K6_IMAGE) run -e VUS=10000 -e DURATION=$(LOAD_DURATION) -

# Emergency CRM Transactional Dispatch Stress Test
test-crm-stress:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Emergency CRM Transactional Lifecycle Stress Test$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@python3 scripts/test/crm_combined_stress_test.py

# Combined Workload Test: Simultaneous Emergency CRM Writes + Tablet Advisory Polling
test-combined:
	$(call guard-not-production)
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Combined Workload: CRM Dispatch + Tablet Polling$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@python3 scripts/test/crm_combined_stress_test.py

# Long-Running Soak Test (Default 2 hours = 7200s, configurable via SOAK_DURATION)
test-soak:
	$(call guard-not-production)
	$(call guard-confirm,Execute long-duration soak test ($(SOAK_DURATION)),yes-run-soak)
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@echo -e "  $(CLR_CYAN)$(CLR_BOLD)NHMP 130 — Extended Soak Test (Duration: $(SOAK_DURATION))$(CLR_RESET)"
	@echo -e "$(CLR_BOLD)======================================================================$(CLR_RESET)"
	@cat scripts/test/k6_workload.js | docker run --rm -i --net=host $(K6_IMAGE) run -e VUS=250 -e DURATION=$(SOAK_DURATION) -
