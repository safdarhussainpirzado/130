import { defineConfig, devices } from '@playwright/test';

/**
 * NHMP 130 CRM — Permanent Playwright Browser Automation Configuration
 * Targets Candidate Edge or local developer test instance.
 */
export default defineConfig({
  testDir: './tests/Browser',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: 1, // Sequential execution for stateful CRM workflows
  timeout: 60000,
  expect: {
    timeout: 5000,
  },
  outputDir: 'test-results/runs',
  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['json', { outputFile: 'test-results/browser-results.json' }],
  ],
  use: {
    baseURL: process.env.APP_URL || process.env.CANDIDATE_URL || 'http://127.0.0.1:8888',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    headless: true,
    viewport: { width: 1440, height: 900 },
    ignoreHTTPSErrors: true,
  },
  projects: [
    {
      name: 'Local Chrome',
      use: {
        ...devices['Desktop Chrome'],
        channel: 'chrome',
        baseURL: process.env.APP_URL || 'http://127.0.0.1:8888',
      },
    },
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
    },
    {
      name: 'Google Chrome',
      use: {
        ...devices['Desktop Chrome'],
        channel: 'chrome',
        storageState: 'test-results/auth-state.json',
      },
      dependencies: ['setup'],
    },
  ],
});

