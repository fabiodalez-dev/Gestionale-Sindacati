// @ts-check
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  timeout: parseInt(process.env.TEST_TIMEOUT || '30000', 10),
  retries: 0,
  reporter: 'line',
  use: {
    baseURL: process.env.TEST_BASE_URL || 'http://localhost:8080',
    headless: true,
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
