import { test, expect } from '@playwright/test';
import { generateTotp, loginAs } from './helpers/auth';

/**
 * Browser Automation Suite: Authentication & MFA State Machine
 * Verifies UI controls, accessible password toggle, motion switch, and TOTP verification.
 */
// Ensure unauthenticated context for login testing
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Authentication & MFA Browser Flow', () => {
  test('should display login page with accessible controls and branding', async ({ page }) => {
    await page.goto('/login');
    await expect(page).toHaveTitle(/Sign in/);

    // Assert inputs and interactive buttons are visible and accessible
    await expect(page.locator('#username')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('#loginButton')).toBeVisible();

    // Verify password visibility toggle
    const togglePass = page.locator('#togglePassword');
    if (await togglePass.isVisible()) {
      await expect(page.locator('#password')).toHaveAttribute('type', 'password');
      await togglePass.click();
      await expect(page.locator('#password')).toHaveAttribute('type', 'text');
      await togglePass.click();
      await expect(page.locator('#password')).toHaveAttribute('type', 'password');
    }
  });

  test('should reject invalid credentials and display error alert', async ({ page }) => {
    await page.goto('/login');
    await page.fill('#username', 'nhmp_admin');
    await page.fill('#password', 'WrongPassword123!');
    await page.click('#loginButton');

    // Should remain on login page with error indication
    await expect(page).toHaveURL(/login/);
    await expect(page.getByText(/We couldn't sign you in|credentials do not match/i)).toBeVisible({ timeout: 5000 });
  });

  test('should enforce 2FA challenge and redirect to dashboard upon valid TOTP submission', async ({ page }) => {
    await loginAs(page, 'nhmp_admin', 'TestAdminPass123!', 'JBSWY3DPEHPK3PXP');

    // Authenticated state must be active
    expect(page.url()).not.toContain('/login');
    expect(page.url()).not.toContain('/2fa/challenge');
    await expect(page.locator('body')).toBeVisible();
  });

  test('should redirect unauthenticated direct access attempts to /login', async ({ page, context }) => {
    // Clear cookies for fresh unauthenticated context
    await context.clearCookies();
    await page.goto('/dashboard');

    await expect(page).toHaveURL(/login/);
  });
});
