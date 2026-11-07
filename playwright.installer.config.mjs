import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/installer',
  timeout: 40_000,
  workers: 1,
  retries: 0,
  use: {
    baseURL: 'http://127.0.0.1:8010',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'fresh-install-desktop', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8010',
    url: 'http://127.0.0.1:8010/installer',
    reuseExistingServer: false,
    timeout: 60_000,
  },
});
