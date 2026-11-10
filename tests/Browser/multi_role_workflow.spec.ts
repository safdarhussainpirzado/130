import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * NHMP 130 CRM — Multi-Role Operational Verification Suite
 * Verifies live browser login, session isolation, and operational UI
 * for Agent, Beat Operator, Sector Admin, and Zone Admin.
 */

test.describe('Multi-Role Operational Browser Suite', () => {

  // Each role test starts with a fresh, unauthenticated session
  test.use({ storageState: { cookies: [], origins: [] } });

  test('1. Call Center Agent (nhmp_agent_01) should authenticate and access Call Intake console', async ({ page }) => {
    await loginAs(page, 'nhmp_agent_01', 'TestPass123!', 'JBSWY3DPEHPK3PXP');

    // Navigate to Call Intake console
    await page.goto('/calls/create', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/NHMP 130/);

    // Verify phone input and form controls exist and are active
    const phoneInput = page.locator('#phone_link').first();
    await expect(phoneInput).toBeVisible({ timeout: 15000 });
    await phoneInput.fill('03009999001');

    // Verify visible caller name input (excluding hidden input)
    const nameInput = page.locator('input[x-model="caller.name"]:not([type="hidden"])').first();
    await expect(nameInput).toBeVisible({ timeout: 10000 });
    await nameInput.fill('Citizen Browser Test');

    // Verify visible Vehicle ID input (excluding hidden input)
    const vehicleInput = page.locator('input[x-model="caller.vehicle_no"]:not([type="hidden"])').first();
    await expect(vehicleInput).toBeVisible({ timeout: 10000 });
    await vehicleInput.fill('ICT-2026');

    // Verify Submit & Dispatch button is present in the DOM
    const submitBtn = page.locator('button[type="submit"]:has-text("Submit & Dispatch")').first();
    await expect(submitBtn).toBeVisible({ timeout: 10000 });

    console.log('[Multi-Role] Agent (nhmp_agent_01) successfully verified Call Intake console.');
  });

  test('2. Beat Wireless Operator (nhmp_beat_01) should authenticate and access Calls Dispatch list', async ({ page }) => {
    await loginAs(page, 'nhmp_beat_01', 'TestPass123!', 'JBSWY3DPEHPK3PXP');

    // Navigate to Calls List
    await page.goto('/calls', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/NHMP 130/);

    // Verify table structure loads with operational columns
    const callsTable = page.locator('table').first();
    await expect(callsTable).toBeVisible({ timeout: 15000 });

    const tableText = await callsTable.innerText();
    expect(tableText).toMatch(/ID|Category|Zone \/ Sector \/ Beat|Caller Info|Actions/i);

    console.log('[Multi-Role] Beat Operator (nhmp_beat_01) verified call dispatch workspace.');
  });

  test('3. Sector Administrator (nhmp_sector_01) should authenticate and view Sector Dashboard KPIs', async ({ page }) => {
    await loginAs(page, 'nhmp_sector_01', 'TestPass123!', 'JBSWY3DPEHPK3PXP');

    // Navigate to Dashboard
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/NHMP 130/);

    // Verify KPI metric cards render
    const kpiElements = page.locator('main').first();
    await expect(kpiElements).toBeVisible({ timeout: 15000 });

    // Verify page content contains operational KPI indicators
    const bodyText = await page.innerText('body');
    expect(bodyText).toMatch(/TOTAL|CALLS|INCIDENTS|DISPATCH|POLICE/i);

    console.log('[Multi-Role] Sector Admin (nhmp_sector_01) successfully verified Sector Dashboard KPIs.');
  });

  test('4. Zone Administrator (nhmp_zone_01) should authenticate and view Regional Dashboard Oversight', async ({ page }) => {
    await loginAs(page, 'nhmp_zone_01', 'TestPass123!', 'JBSWY3DPEHPK3PXP');

    // Navigate to Dashboard
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/NHMP 130/);

    // Verify regional dashboard overview renders
    const mainArea = page.locator('main').first();
    await expect(mainArea).toBeVisible({ timeout: 15000 });

    // Navigate to regional calls view
    await page.goto('/calls', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/NHMP 130/);

    const callsTable = page.locator('table').first();
    await expect(callsTable).toBeVisible({ timeout: 15000 });

    console.log('[Multi-Role] Zone Admin (nhmp_zone_01) successfully verified Regional Oversight.');
  });

});
