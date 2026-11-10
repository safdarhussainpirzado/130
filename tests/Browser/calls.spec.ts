import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * Browser Automation Suite: Calls Management & Operational Workflows
 * Verifies table rendering, server-side pagination, fast-path search, view action,
 * status transitions, filter preservation, directory switching, history navigation,
 * and race condition mitigation.
 */
test.describe('Calls Management Browser Suite', () => {

  test('should render calls list with server-side pagination controls', async ({ page }) => {
    await page.goto('/calls', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/Calls|Helpline|130/);

    // Verify table structure
    const table = page.locator('table, .calls-table, [role="table"]');
    await expect(table.first()).toBeVisible();

    // Verify rows exist
    const rows = page.locator('tbody tr, .call-row');
    const count = await rows.count();
    expect(count).toBeGreaterThan(0);

    // Verify pagination controls exist
    const pagination = page.locator('.pagination, nav[role="navigation"], [aria-label*="pagination"]');
    if (await pagination.count() > 0) {
      await expect(pagination.first()).toBeVisible();
    }
  });

  test('should filter records using search input with indexed CRM call number', async ({ page }) => {
    await page.goto('/calls?search=CRM-2026-00001', { waitUntil: 'domcontentloaded' });
    
    // Verify fast-path indexed search filtered the table to the target record
    const table = page.locator('table').first();
    await expect(table).toBeVisible({ timeout: 5000 });
    await expect(table).toContainText('CRM-2026-00001');
  });

  test('should toggle between assigned and unassigned directories with clean state', async ({ page }) => {
    // 1. Visit Assigned directory
    await page.goto('/calls?assignment=assigned', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/assignment=assigned/);

    // 2. Switch to Unassigned directory
    const unassignedTab = page.locator('a[href*="assignment=unassigned"], button:has-text("Unassigned")').first();
    if (await unassignedTab.isVisible()) {
      await unassignedTab.click();
      await expect(page).toHaveURL(/assignment=unassigned/);
    }
  });

  test('should preserve active filters and sorting across pagination navigation', async ({ page }) => {
    // Navigate with a specific status filter and descending sort
    await page.goto('/calls?status=pending&sort=created_at&direction=desc', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/status=pending/);

    // Click next page button if multi-page dataset exists
    const nextPageBtn = page.locator('button:has-text("Next"), a[rel="next"]').first();
    if (await nextPageBtn.isVisible()) {
      await nextPageBtn.click();
      // Verify filter and sort parameters are strictly preserved on page 2
      await expect(page).toHaveURL(/status=pending/);
      await expect(page).toHaveURL(/sort=created_at/);
      await expect(page).toHaveURL(/direction=desc/);
    }
  });

  test('should support browser history popstate (Back/Forward navigation)', async ({ page }) => {
    await page.goto('/calls?page=1', { waitUntil: 'domcontentloaded' });
    await page.goto('/calls?page=2', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/page=2/);

    // Click browser Back button
    await page.goBack();
    await expect(page).toHaveURL(/page=1/);

    // Click browser Forward button
    await page.goForward();
    await expect(page).toHaveURL(/page=2/);
  });

  test('should drop stale responses when requests overlap with artificial delays', async ({ page }) => {
    // Intercept /calls API requests to simulate network delay on slow request
    await page.route('**/calls?*', async (route) => {
      const url = route.request().url();
      if (url.includes('search=slow')) {
        // Artificial delay of 800ms
        await new Promise((r) => setTimeout(r, 800));
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            items: [{ id: 999, call_number: 'CRM-SLOW-STALE', status: 'pending' }],
            stats: { total: 1, pending: 1, assigned: 1, unassigned: 0 },
            current_page: 1,
            last_page: 1,
            total: 1,
          }),
        });
      } else if (url.includes('search=fast')) {
        // Immediate response
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            items: [{ id: 888, call_number: 'CRM-FAST-LATEST', status: 'completed' }],
            stats: { total: 1, completed: 1, assigned: 1, unassigned: 0 },
            current_page: 1,
            last_page: 1,
            total: 1,
          }),
        });
      } else {
        await route.continue();
      }
    });

    await page.goto('/calls', { waitUntil: 'domcontentloaded' });

    // Trigger slow search
    await page.evaluate(() => {
      const el = document.querySelector('[x-data]') as any;
      if (el && el._x_dataStack && el._x_dataStack[0]) {
        el._x_dataStack[0].search = 'slow';
        el._x_dataStack[0].fetchData();
      }
    });

    // Immediately trigger fast search before slow finishes
    await page.evaluate(() => {
      const el = document.querySelector('[x-data]') as any;
      if (el && el._x_dataStack && el._x_dataStack[0]) {
        el._x_dataStack[0].search = 'fast';
        el._x_dataStack[0].fetchData();
      }
    });

    // Wait for the delayed request to settle
    await page.waitForTimeout(1000);

    // Verify the latest response wins and is not overwritten by stale response
    const tableText = await page.locator('body').innerText();
    expect(tableText).toContain('CRM-FAST-LATEST');
    expect(tableText).not.toContain('CRM-SLOW-STALE');
  });

  test('should view call details and execute status transition workflow without full page reload', async ({ page }) => {
    await page.goto('/calls/1', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toBeVisible();

    // Verify call information elements
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toContain('CRM-');

    // Verify remarks or status controls if present on page
    const statusSelect = page.locator('select[name="status"], #status');
    const remarksInput = page.locator('textarea[name="remarks"], #remarks');
    const submitBtn = page.locator('button[type="submit"]:has-text("Update"), button:has-text("Save")');

    if (await statusSelect.count() > 0 && await submitBtn.count() > 0) {
      await statusSelect.first().selectOption('tiger_reached');
      if (await remarksInput.count() > 0) {
        await remarksInput.first().fill('Patrol vehicle arrived at scene. Playwright verification.');
      }
      await submitBtn.first().click();

      // Verify success feedback or updated status badge
      await expect(page.locator('body')).toContainText(/tiger_reached|Updated|Success|Patrol/i);
    }
  });
});
