import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests',
  forbidOnly: true,
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 30_000,
  // Copy failures out after Playwright exits; trace writes to Windows mounts can stall teardown.
  reporter: [
    ['list'],
    ['html', { outputFolder: '/tmp/blukios-e2e/report', open: 'never' }],
  ],
  outputDir: '/tmp/blukios-e2e/test-results',
  use: {
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:18080',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    serviceWorkers: 'block',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
