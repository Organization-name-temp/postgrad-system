import { defineConfig, devices } from '@playwright/test'

// The headline smoke needs the whole stack, so Playwright boots BOTH servers:
// Laravel (8000) and Vite (5173). The SPA reaches the API through the Vite
// proxy, exactly as in real dev. It exercises auth, the catalog browse tree and
// Course detail, so it needs Postgres up (`docker compose up`) and the dev DB
// migrated + seeded (`php artisan migrate --seed`) so the CatalogSeeder data exists.
export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  // The whole suite runs against ONE `php artisan serve`, and PHP's built-in server answers a
  // single request at a time (on Windows it cannot fork workers at all). Past two browsers the
  // API is the bottleneck, not the CPU: requests queue behind each other and a sign-in POST
  // blows its assertion timeout, which reads as a flaky login rather than as saturation. Two
  // workers is what the stack actually sustains — raising it makes the suite slower *and* red.
  workers: 2,
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
