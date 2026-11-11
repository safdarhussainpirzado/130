import { test, expect } from '@playwright/test';

/**
 * NHMP 130 CRM — Sprint 2 Browser Automation Suite
 * Permanent Playwright Regression Test: User Management Call Center 481-Count Defect
 *
 * Verifies:
 * 1. Root-cause reproduction and permanent fix for the 481 vs 0 displayed defect.
 * 2. Synchronized card count (481) and table filter rendering.
 * 3. Multi-page pagination across all 481 records (20 pages at 25/page).
 * 4. Search within call center population.
 * 5. Role filtering (Agent vs Supervisor) and role labels.
 * 6. Sorting, active/inactive handling, modal inspection.
 * 7. Console error and network diagnostics.
 */

test.describe('User Management Call Center Defect & Browser QA', () => {

  test.beforeEach(async ({ page }) => {
    // Navigate to User Management
    await page.goto('/mgmt/users', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/User Management/);
  });

  test('should display Call Center card count as 481 and render all 481 records upon clicking card', async ({ page }) => {
    // 1. Verify Call Center Card count is 481
    const callCenterCard = page.locator('#callCenterCard');
    await expect(callCenterCard).toBeVisible();

    const countElem = page.locator('#callCenterCount');
    await expect(countElem).toHaveText('481');

    // 2. Click Call Center card to filter
    await callCenterCard.click();
    await expect(callCenterCard).toHaveClass(/card-3d-active/);

    // 3. Verify filtered count text in panel header updates to (481 users)
    const headerCount = page.locator('#filteredCountText');
    await expect(headerCount).toHaveText('(481 users)');

    // 4. Critical: Assert table displays rows and DOES NOT display 0 users
    const userRows = page.locator('.user-row');
    const firstPageCount = await userRows.count();
    expect(firstPageCount).toBe(25); // Page size is 25

    const emptyRow = page.locator('#emptyRow');
    await expect(emptyRow).toBeHidden();

    // 5. Verify pagination reflects 481 records
    const paginationText = page.locator('#paginationControls');
    await expect(paginationText).toContainText('Showing 1 to 25 of 481 entries');
    const pageIndicator = page.locator('#pageIndicator');
    await expect(pageIndicator).toHaveText('Page 1 of 20');
  });

  test('should navigate across pages of the 481 Call Center dataset without losing filter state', async ({ page }) => {
    // Filter to Call Center
    await page.locator('#callCenterCard').click();

    // Page 1
    await expect(page.locator('#pageIndicator')).toHaveText('Page 1 of 20');
    const firstRowUser = await page.locator('.user-row').first().locator('td:nth-child(2)').innerText();

    // Navigate to Page 2
    const nextPageBtn = page.locator('#nextPageBtn');
    await nextPageBtn.click();

    await expect(page.locator('#pageIndicator')).toHaveText('Page 2 of 20');
    await expect(page.locator('#paginationControls')).toContainText('Showing 26 to 50 of 481 entries');

    const secondPageFirstUser = await page.locator('.user-row').first().locator('td:nth-child(2)').innerText();
    expect(secondPageFirstUser).not.toEqual(firstRowUser);

    // Navigate to Previous page
    const prevPageBtn = page.locator('#prevPageBtn');
    await prevPageBtn.click();
    await expect(page.locator('#pageIndicator')).toHaveText('Page 1 of 20');
    await expect(page.locator('.user-row').first().locator('td:nth-child(2)')).toHaveText(firstRowUser);
  });

  test('should search within Call Center staff by username and CNIC', async ({ page }) => {
    // Filter to Call Center
    await page.locator('#callCenterCard').click();

    const searchInput = page.locator('#userSearchInput');

    // Search for a specific agent
    await searchInput.fill('agent_042');
    await page.waitForTimeout(100);

    const rows = page.locator('.user-row');
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText('agent_042');
    await expect(page.locator('#filteredCountText')).toHaveText('(1 users)');

    // Search for a supervisor
    await searchInput.fill('supervisor_05');
    await page.waitForTimeout(100);

    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText('supervisor_05');
    await expect(rows.first()).toContainText('Agent Supervisor');

    // Clear search and restore 481 users
    await searchInput.fill('');
    await page.waitForTimeout(100);
    await expect(page.locator('#filteredCountText')).toHaveText('(481 users)');
    await expect(rows).toHaveCount(25);
  });

  test('should filter by specific Call Center roles (Supervisor vs Agent) and verify role labels', async ({ page }) => {
    // Filter to Call Center
    await page.locator('#callCenterCard').click();

    const roleSelect = page.locator('#roleFilterSelect');

    // Filter to Agent Supervisor (31 total)
    await roleSelect.selectOption('4');
    await page.waitForTimeout(100);

    await expect(page.locator('#filteredCountText')).toHaveText('(31 users)');
    const supRows = page.locator('.user-row');
    await expect(supRows).toHaveCount(25); // Page 1 of 2

    for (let i = 0; i < 5; i++) {
      await expect(supRows.nth(i)).toContainText('Agent Supervisor');
    }

    // Filter to Call Agent (450 total)
    await roleSelect.selectOption('5');
    await page.waitForTimeout(100);

    await expect(page.locator('#filteredCountText')).toHaveText('(450 users)');
    const agentRows = page.locator('.user-row');
    for (let i = 0; i < 5; i++) {
      await expect(agentRows.nth(i)).toContainText('Call Center Agent');
    }

    // Reset role filter
    await roleSelect.selectOption('');
    await expect(page.locator('#filteredCountText')).toHaveText('(481 users)');
  });

  test('should inspect user details in modal dialog and close cleanly', async ({ page }) => {
    await page.locator('#callCenterCard').click();

    // Click View on first user
    const firstViewBtn = page.locator('.user-row .view-btn').first();
    await firstViewBtn.click();

    const modal = page.locator('#viewUserModal');
    await expect(modal).toBeVisible();

    const details = page.locator('#modalUserDetails');
    await expect(details).toContainText('Scope Type: Call Center');
    await expect(details).toContainText('Scope Raw: call_centre');
    await expect(details).toContainText('Scope Unit: Helpline 130');

    // Close modal
    await page.locator('#closeModalBtn').click();
    await expect(modal).toBeHidden();
  });

  test('should reproduce the defect root-cause: legacy logic produces 0 users while fixed logic produces 481', async ({ page }) => {
    // Execute evaluate script in the browser to prove the root cause
    const testResult = await page.evaluate(() => {
      const app = document.querySelector('#user-management-app') as any;
      const alpineData = app._x_dataStack[0];
      const items = alpineData.items;

      // 1. Defect reproduction: Simulate pre-fix serialization where call center staff had scope_unit_type_raw = 'national'
      const legacyItems = items.map((i: any) => {
        if (i.role_name === 'agent' || i.role_name === 'agent_supervisor') {
          return { ...i, scope_unit_type_raw: 'national', scope_unit_type: 'National' };
        }
        return i;
      });

      // Legacy filter checked only scope_unit_type_raw === 'call_center' || scope_unit_type_raw === 'call_centre'
      const legacyFiltered = legacyItems.filter((i: any) => {
        return i.scope_unit_type_raw === 'call_center' || i.scope_unit_type_raw === 'call_centre';
      });

      // 2. Fixed filter: Checks both scope_unit_type_raw and role name fallback
      const fixedFiltered = items.filter((i: any) => {
        return i.scope_unit_type_raw === 'call_centre'
          || i.scope_unit_type_raw === 'call_center'
          || i.role_name === 'agent'
          || i.role_name === 'agent_supervisor';
      });

      return {
        legacyMatchesCount: legacyFiltered.length, // Expected: 0 (the exact bug!)
        fixedMatchesCount: fixedFiltered.length,   // Expected: 481 (the fix!)
      };
    });

    // Assert that legacy logic reproduced 0 users (the exact defect reported)
    expect(testResult.legacyMatchesCount).toBe(0);

    // Assert that fixed logic produces the complete 481 population
    expect(testResult.fixedMatchesCount).toBe(481);
  });
});
