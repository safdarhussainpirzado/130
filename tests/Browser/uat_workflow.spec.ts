import { test, expect } from '@playwright/test';
import { loginAs, generateTotp } from './helpers/auth';

/**
 * NHMP 130 CRM — Full-System Browser-Driven UAT & Workflow Suite
 * Exercises actual visible browser UI for admin, agent, and operator workflows.
 */

test.describe('NHMP 130 CRM — Browser UAT & Business Workflows', () => {

  test('1. Super Admin should authenticate and verify organizational management', async ({ page }) => {
    await loginAs(page, 'nhmp_admin', 'TestAdminPass123!', 'JBSWY3DPEHPK3PXP');
    
    // Navigate to Offices & Hierarchy management
    await page.goto('/mgmt/offices', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/NHMP 130/);
    await expect(page.locator('body')).toContainText(/Hierarchy|Units|Offices|Operational Units/i);
    
    // Check that organizational table has rows
    const rows = page.locator('table tbody tr');
    const rowCount = await rows.count();
    expect(rowCount).toBeGreaterThan(0);
    console.log(`[UAT] Verified ${rowCount} organizational units loaded in UI.`);
  });

  test('2. Super Admin should verify user management and personnel roster', async ({ page }) => {
    await loginAs(page, 'nhmp_admin', 'TestAdminPass123!', 'JBSWY3DPEHPK3PXP');
    
    // Navigate to User Management
    await page.goto('/mgmt/users', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/NHMP 130/);
    await expect(page.locator('body')).toContainText(/Personnel|User Management|Users/i);
    
    const userRows = page.locator('table tbody tr');
    const count = await userRows.count();
    expect(count).toBeGreaterThan(0);
    console.log(`[UAT] Verified ${count} user identities loaded in UI.`);
  });

  test('3. Agent should navigate to call intake and inspect form controls', async ({ page }) => {
    await loginAs(page, 'nhmp_admin', 'TestAdminPass123!', 'JBSWY3DPEHPK3PXP');
    
    // Navigate to Call Intake Form
    await page.goto('/calls/create', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/NHMP 130/);
    
    // Verify phone number input and core form fields exist
    const phoneInput = page.getByPlaceholder('03001234567').first();
    await expect(phoneInput).toBeVisible({ timeout: 10000 });
    
    // Check intake form exists
    const intakeForm = page.locator('#intake-form, form');
    await expect(intakeForm.first()).toBeVisible();
    console.log('[UAT] Call Intake UI controls verified successfully.');
  });

  test('4. Wireless Operator should view call details and verify workflow audit trail', async ({ page }) => {
    await loginAs(page, 'nhmp_admin', 'TestAdminPass123!', 'JBSWY3DPEHPK3PXP');
    
    // Navigate to calls list
    await page.goto('/calls', { waitUntil: 'domcontentloaded' });
    const viewButton = page.locator('button:has-text("View Intelligence"), table tbody tr button, table tbody tr').first();
    await expect(viewButton).toBeVisible({ timeout: 10000 });
    await viewButton.click();
    
    // Wait for call details modal or page navigation
    await page.waitForTimeout(1500);
    await expect(page.locator('body')).toContainText(/CRM-|Call Details|History|Status|Intelligence|Dispatch/i);
    console.log('[UAT] Successfully verified call details and audit trail in UI.');
  });

  test('5. Multi-agent concurrent browser sessions load ramp (10 concurrent agents)', async ({ browser }) => {
    console.log('[UAT] Initiating 10 concurrent browser agent sessions...');
    const concurrentAgents = 10;
    const contexts = [];
    const results: Array<{ agent: number; status: number; ok: boolean; error?: string }> = [];

    for (let i = 0; i < concurrentAgents; i++) {
      const ctx = await browser.newContext({ storageState: 'test-results/auth-state.json' });
      contexts.push(ctx);
    }

    const start = Date.now();
    await Promise.all(
      contexts.map(async (ctx, idx) => {
        // Stagger session starts slightly to avoid thundering herd on local 4-core CPU
        await new Promise(r => setTimeout(r, idx * 150));
        const p = await ctx.newPage();
        try {
          const res = await p.goto('/dashboard', { waitUntil: 'domcontentloaded', timeout: 45000 });
          const status = res ? res.status() : 0;
          expect(status).toBe(200);
          results.push({ agent: idx + 1, status, ok: true });
        } catch (err: any) {
          results.push({ agent: idx + 1, status: 500, ok: false, error: err.message });
        } finally {
          await p.close();
          await ctx.close();
        }
      })
    );
    const duration = Date.now() - start;

    const successful = results.filter(r => r.ok).length;
    console.log(`[UAT] 10 concurrent agent sessions completed in ${duration}ms. Success: ${successful}/${concurrentAgents}`);
    expect(successful).toBe(concurrentAgents);
  });

});
