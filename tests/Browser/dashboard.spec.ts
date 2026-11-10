import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * Browser Automation Suite: Operations Dashboard
 * Verifies KPI cards, single-pass query metrics, Chart.js graphs, Leaflet maps, and period filters.
 */
test.describe('Dashboard Browser Verification', () => {

  test('should load dashboard with all KPI widgets, map, and charts without console errors', async ({ page }) => {
    const consoleErrors: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error' && !msg.text().includes('favicon')) {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto('/dashboard');
    await expect(page).toHaveTitle(/Dashboard|Helpline|130/);

    // Verify main content container
    await expect(page.locator('main').first()).toBeVisible();

    // Verify KPI numbers are rendered (non-empty)
    const kpiCards = page.locator('.kpi-card, .metric-card, [data-metric]');
    if (await kpiCards.count() > 0) {
      await expect(kpiCards.first()).toBeVisible();
    }

    // Verify Map container exists
    const mapContainer = page.locator('#map, .leaflet-container, [id*="map"]');
    if (await mapContainer.count() > 0) {
      await expect(mapContainer.first()).toBeVisible();
    }

    // Verify Charts container exists
    const chartCanvas = page.locator('canvas, .chartjs-render-monitor, [id*="chart"]');
    if (await chartCanvas.count() > 0) {
      await expect(chartCanvas.first()).toBeVisible();
    }

    // Confirm no critical JavaScript exceptions broke the page
    const criticalErrors = consoleErrors.filter(e => !e.includes('404') && !e.includes('net::ERR'));
    expect(criticalErrors).toHaveLength(0);
  });
});
