import { test as setup, expect } from '@playwright/test';
import { generateTotp } from '../helpers/auth';
import * as fs from 'fs';

const authFile = 'test-results/auth-state.json';

/**
 * Setup project: Authenticates once as nhmp_admin and persists storageState.
 * All subsequent suites (dashboard, calls, reports) reuse this session.
 */
setup('authenticate as nhmp_admin', async ({ page }) => {
  if (fs.existsSync(authFile)) {
    const stats = fs.statSync(authFile);
    const ageSeconds = (Date.now() - stats.mtimeMs) / 1000;
    if (ageSeconds < 7200) {
      console.log(`Reusing existing valid auth session (${Math.round(ageSeconds)}s old).`);
      return;
    }
  }

  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await expect(page).toHaveTitle(/Sign in/);

  await page.fill('#username', 'nhmp_admin');
  await page.fill('#password', 'TestAdminPass123!');
  await page.click('#loginButton');

  // Wait for 2FA challenge or direct navigation
  await page.waitForURL((url) => url.pathname.includes('/2fa/challenge') || url.pathname.includes('/dashboard'), { timeout: 25000 });

  if (page.url().includes('/2fa/challenge')) {
    const code = generateTotp('JBSWY3DPEHPK3PXP');
    const codeInput = page.locator('#code, input[name="code"]').first();
    await codeInput.waitFor({ state: 'visible', timeout: 10000 });
    await codeInput.fill(code);
    await page.click('#loginButton, button[type="submit"]');

    await page.waitForURL((url) => !url.pathname.includes('/2fa/challenge') && !url.pathname.includes('/login'), { timeout: 25000 });
  }

  await expect(page.locator('body')).toBeVisible();

  // Save authenticated session state for all test specs
  await page.context().storageState({ path: authFile });
});
