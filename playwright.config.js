// @ts-check
const { defineConfig, devices } = require('@playwright/test');

const parsedTimeout = parseInt(process.env.TEST_TIMEOUT ?? '30000', 10);
const timeout = Number.isFinite(parsedTimeout) && parsedTimeout > 0 ? parsedTimeout : 30000;

module.exports = defineConfig({
  testDir: './tests',
  timeout,
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
