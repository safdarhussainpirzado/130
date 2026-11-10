import { Page, expect } from '@playwright/test';
import * as crypto from 'node:crypto';

/**
 * Generate a 6-digit RFC 6238 TOTP token for automated browser testing.
 */
export function generateTotp(secretBase32: string): string {
  const cleaned = secretBase32.trim().toUpperCase().replace(/ /g, '');
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let binary = '';
  for (let i = 0; i < cleaned.length; i++) {
    const val = alphabet.indexOf(cleaned[i]);
    if (val !== -1) {
      binary += val.toString(2).padStart(5, '0');
    }
  }

  const bytes: number[] = [];
  for (let i = 0; i + 8 <= binary.length; i += 8) {
    bytes.push(parseInt(binary.slice(i, i + 8), 2));
  }
  const key = Buffer.from(bytes);

  const counter = Math.floor(Date.now() / 1000 / 30);
  const buf = Buffer.alloc(8);
  buf.writeBigInt64BE(BigInt(counter), 0);

  const hmac = crypto.createHmac('sha1', key).update(buf).digest();
  const offset = hmac[hmac.length - 1] & 0x0f;
  const code = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1000000;
  return code.toString().padStart(6, '0');
}

/**
 * Complete browser login including username/password and TOTP 2FA challenge.
 */
export async function loginAs(
  page: Page,
  username: string = 'nhmp_admin',
  password: string = 'TestAdminPass123!',
  totpSecret: string = 'JBSWY3DPEHPK3PXP'
): Promise<void> {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  // If session is already authenticated, Laravel redirects to /dashboard immediately
  if (!page.url().includes('/login')) {
    return;
  }

  await expect(page).toHaveTitle(/Sign in/);

  await page.fill('#username', username);
  await page.fill('#password', password);
  await page.click('#loginButton');

  // Check if routed to 2FA challenge
  await page.waitForURL((url) => url.pathname.includes('/2fa/challenge') || url.pathname.includes('/dashboard') || url.pathname.includes('/calls'), { timeout: 20000 });

  if (page.url().includes('/2fa/challenge')) {
    const code = generateTotp(totpSecret);
    const codeInput = page.locator('#code, input[name="code"]').first();
    await codeInput.waitFor({ state: 'visible', timeout: 10000 });
    await codeInput.fill(code);
    await page.click('#loginButton, button[type="submit"]');

    // Wait for successful redirection
    await page.waitForURL((url) => !url.pathname.includes('/2fa/challenge') && !url.pathname.includes('/login'), { timeout: 20000 });
  }
}
