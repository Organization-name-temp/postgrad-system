import { defineConfig, devices } from '@playwright/test'

// The headline smoke needs the whole stack, so Playwright boots BOTH servers:
// Laravel (8000) and Vite (5173). The SPA reaches the API through the Vite
// proxy, exactly as in real dev. It exercises auth, the catalog browse tree and
// Course detail, so it needs Postgres up (`docker compose up`) and the dev DB
// migrated + seeded (`php artisan migrate --seed`) so the CatalogSeeder data exists.
export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  // The catalog headline flow (login → browse → detail → two axe scans) is heavy;
  // run in parallel with the four RTL-font-loading style combos it can brush past
  // the 30s default under CPU contention, so give every test a roomier ceiling.
  timeout: 60_000,
  // `list` for console output; `html` bundles the per-combo screenshot
  // attachments (style smoke) into a report CI uploads for human review.
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: 'http://localhost:5173',
    trace: 'on-first-retry',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: [
    {
      command: 'php artisan serve --host=127.0.0.1 --port=8000',
      cwd: '../backend',
      url: 'http://127.0.0.1:8000/up',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
    },
    {
      command: 'npm run dev',
      url: 'http://localhost:5173',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
    },
  ],
})
