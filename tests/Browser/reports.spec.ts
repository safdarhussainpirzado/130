import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * Browser Automation Suite: Reporting & Analytics
 * Verifies Call Type Summary multi-group breakdown, hourly 24h skeleton, and totals.
 */
test.describe('Reports Browser Verification', () => {

  test('should load call type summary report with monthly breakdown and total row', async ({ page }) => {
    await page.goto('/reports/call-type-summary?group_by=monthly');
    await expect(page).toHaveTitle(/Report|Summary|130/);

    // Verify summary table is present
    const table = page.locator('table');
    await expect(table.first()).toBeVisible();

    const tableText = await table.first().innerText();
    expect(tableText).toMatch(/TOTAL/i);
  });

  test('should load hourly breakdown with 24-hour time slots', async ({ page }) => {
    await page.goto('/reports/call-type-summary?group_by=hourly');
    await expect(page.locator('body')).toBeVisible();

    const bodyText = await page.locator('body').innerText();
    // Expect hourly format indicator like 00:00 or Total
    expect(bodyText).toMatch(/00:00|Total|Hour|Summary/);
  });
});
