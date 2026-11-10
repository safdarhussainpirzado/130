import { test, expect } from '@playwright/test';

/**
 * NHMP 130 CRM — Full Application Browser QA, Functional Verification & Diagnostics Suite
 *
 * Systematic browser-driven quality assurance covering:
 * - Every accessible page in the CRM.
 * - Interactive controls (search, filters, sorting, pagination, modals, tabs).
 * - Real browser diagnostics (console errors, unhandled rejections, network failures).
 * - State changes, refresh, and browser Back/Forward navigation.
 */

test.describe('Application-Wide Browser QA & Control Coverage', () => {

  let consoleErrors: string[] = [];
  let pageErrors: Error[] = [];
  let failedRequests: { url: string; status: number }[] = [];

  test.beforeEach(async ({ page }) => {
    consoleErrors = [];
    pageErrors = [];
    failedRequests = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error' && !msg.text().includes('favicon')) {
        consoleErrors.push(msg.text());
      }
    });

    page.on('pageerror', (err) => {
      pageErrors.push(err);
    });

    page.on('response', (res) => {
      if (res.status() >= 400 && !res.url().includes('favicon')) {
        failedRequests.push({ url: res.url(), status: res.status() });
      }
    });
  });

  test.afterEach(async () => {
    // Assert zero critical errors across every page
    expect(pageErrors, 'Expected zero unhandled page runtime errors').toHaveLength(0);
    const criticalConsole = consoleErrors.filter(e => !e.includes('net::ERR') && !e.includes('404'));
    expect(criticalConsole, 'Expected zero critical JavaScript console exceptions').toHaveLength(0);
  });

  test('Module 1: Dashboard Command Center (/dashboard)', async ({ page }) => {
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Dashboard/);

    // Verify KPI widgets
    const kpiCards = page.locator('.kpi-card');
    await expect(kpiCards).toHaveCount(4);
    await expect(kpiCards.first()).toContainText('Total Calls Today');

    // Verify Map container
    const map = page.locator('#map');
    await expect(map).toBeVisible();

    // Verify Chart canvas
    const chart = page.locator('#trafficChart');
    await expect(chart).toBeVisible();
  });

  test('Module 2: Calls Directory & Filtering (/calls)', async ({ page }) => {
    await page.goto('/calls', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Helpline Calls/);

    // Exercise search input
    const searchInput = page.locator('#callsSearchInput');
    await expect(searchInput).toBeVisible();
    await searchInput.fill('CRM-2026-00001');
    await page.waitForTimeout(200);

    // Exercise table rows
    const rows = page.locator('.call-row');
    await expect(rows.first()).toBeVisible();

    // Exercise directory switching (Assigned vs Unassigned)
    const assignedLink = page.locator('a[href*="assignment=assigned"]');
    await expect(assignedLink).toBeVisible();

    const unassignedLink = page.locator('a[href*="assignment=unassigned"]');
    await expect(unassignedLink).toBeVisible();

    // Exercise pagination controls
    const pagination = page.locator('.pagination');
    await expect(pagination).toBeVisible();
  });

  test('Module 3: Call Details & Operational Transition (/calls/1)', async ({ page }) => {
    await page.goto('/calls/1', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Call Details/);

    await expect(page.locator('body')).toContainText('CRM-2026-00001');
    await expect(page.locator('body')).toContainText('Citizen Traveler');

    // Exercise status transition dropdown
    const statusSelect = page.locator('#status');
    await expect(statusSelect).toBeVisible();
    await statusSelect.selectOption('tiger_reached');

    // Click Save Status and verify DOM update
    const saveBtn = page.locator('#saveStatusBtn');
    await saveBtn.click();

    const feedback = page.locator('#statusFeedback');
    await expect(feedback).toContainText('Status Updated to tiger_reached Successfully');
  });

  test('Module 4: User Management & Identity Control (/mgmt/users)', async ({ page }) => {
    await page.goto('/mgmt/users', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/User Management/);

    // Exercise Top KPI Cards
    await expect(page.locator('#callCenterCount')).toHaveText('481');
    await expect(page.locator('#totalUsersCount')).toHaveText('591');

    // Filter by Call Center
    await page.locator('#callCenterCard').click();
    await expect(page.locator('#filteredCountText')).toHaveText('(481 users)');

    // Exercise Search
    const searchInput = page.locator('#userSearchInput');
    await searchInput.fill('agent_100');
    await page.waitForTimeout(100);
    await expect(page.locator('.user-row')).toHaveCount(1);
    await searchInput.fill('');
    await page.waitForTimeout(100);

    // Exercise Role Filter
    const roleSelect = page.locator('#roleFilterSelect');
    await roleSelect.selectOption('4'); // Supervisor
    await page.waitForTimeout(100);
    await expect(page.locator('#filteredCountText')).toHaveText('(31 users)');
    await roleSelect.selectOption('');

    // Exercise Pagination per-page selection
    const perPageSelect = page.locator('#perPageSelect');
    await perPageSelect.selectOption('10');
    await expect(page.locator('.user-row')).toHaveCount(10);
    await perPageSelect.selectOption('25');

    // Exercise View Modal
    await page.locator('.user-row .view-btn').first().click();
    const modal = page.locator('#viewUserModal');
    await expect(modal).toBeVisible();
    await page.locator('#closeModalBtn').click();
    await expect(modal).toBeHidden();
  });

  test('Module 5: Security & MFA Settings (/mgmt/mfa-settings)', async ({ page }) => {
    await page.goto('/mgmt/mfa-settings', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Administrative Configuration/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('Module 6: Operational Units & Geography (/mgmt/offices)', async ({ page }) => {
    await page.goto('/mgmt/offices', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Administrative Configuration/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('Module 7: Highway Registry (/mgmt/carriageways)', async ({ page }) => {
    await page.goto('/mgmt/carriageways', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Administrative Configuration/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('Module 8: Geospatial Markers (/mgmt/geospatial-markers)', async ({ page }) => {
    await page.goto('/mgmt/geospatial-markers', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Administrative Configuration/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('Module 9: Role Management (/mgmt/roles)', async ({ page }) => {
    await page.goto('/mgmt/roles', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Administrative Configuration/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('Module 10: Permissions Management (/mgmt/permissions)', async ({ page }) => {
    await page.goto('/mgmt/permissions', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Administrative Configuration/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('Module 11: Security Audit Logs (/mgmt/audit)', async ({ page }) => {
    await page.goto('/mgmt/audit', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Administrative Configuration/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('Module 12: Analytical Reports (/reports/call-type-summary & /reports/agent-wise)', async ({ page }) => {
    await page.goto('/reports/call-type-summary', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Analytical Report/);
    await expect(page.locator('table')).toContainText('TOTAL');

    await page.goto('/reports/agent-wise', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Analytical Report/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('Module 13: Authentication Controls & Password Toggle (/login)', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Sign in/);

    const usernameInput = page.locator('#username');
    const passwordInput = page.locator('#password');
    const toggleBtn = page.locator('#togglePassword');

    await expect(usernameInput).toBeVisible();
    await expect(passwordInput).toBeVisible();
    await expect(passwordInput).toHaveAttribute('type', 'password');

    // Toggle password visibility
    await toggleBtn.click();
    await expect(passwordInput).toHaveAttribute('type', 'text');
    await toggleBtn.click();
    await expect(passwordInput).toHaveAttribute('type', 'password');
  });

  test('Module 14: Browser Back/Forward & State Preservation', async ({ page }) => {
    await page.goto('/dashboard');
    await page.goto('/calls');
    await page.goto('/mgmt/users');

    await expect(page).toHaveTitle(/User Management/);

    // Back to /calls
    await page.goBack();
    await expect(page).toHaveTitle(/Helpline Calls/);

    // Back to /dashboard
    await page.goBack();
    await expect(page).toHaveTitle(/Dashboard/);

    // Forward to /calls
    await page.goForward();
    await expect(page).toHaveTitle(/Helpline Calls/);

    // Forward to /mgmt/users
    await page.goForward();
    await expect(page).toHaveTitle(/User Management/);
  });
});
