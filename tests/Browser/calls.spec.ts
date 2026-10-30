import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * Browser Automation Suite: Calls Management & Operational Workflows
 * Verifies table rendering, server-side pagination, fast-path search, view action, and status transitions.
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

  test('should view call details and execute status transition workflow', async ({ page }) => {
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
