import { defineConfig, devices } from '@playwright/test'

// The headline smoke needs the whole stack, so Playwright boots BOTH servers:
// Laravel (8000) and Vite (5173). The SPA reaches the API through the Vite
// proxy, exactly as in real dev. It exercises auth, the catalog browse tree and
// Course detail, so it needs Postgres up (`docker compose up`) and the dev DB
// migrated + seeded (`php artisan migrate --seed`) so the CatalogSeeder data exists.
export default defineConfig({
  testDir: './e2e',
  // **The suite is serial, deliberately.** These are full-stack integration tests sharing ONE
  // database and ONE set of seeded accounts, so concurrency is not a throughput question — it is
  // a correctness one:
  //
  // - **The UI language is per-user server state.** The switcher persists `preferred_locale`, and
  //   every authenticated page load re-applies it from `/me`. So a bilingual axe combo that
  //   selects Arabic flips the language for *every* concurrently-running test signed in as the
  //   same seeded account, mid-flow, after it has already asserted `dir`. The accounts overlap
  //   heavily across specs (r04, r05, `student.smoke`, `interviewer.hoda`), which is what made
  //   this show up in CI as one arbitrary combo failing on an English locator while its three
  //   siblings passed.
  // - **The headline smokes mutate what the scans read** — signing a Sheet, finalizing it, running
  //   the Incomplete→Fail-Withdrawal conversion — so a parallel scan can observe a half-applied
  //   world.
  // - A secondary reason, and the one ticket 09 mistook for the whole story: the suite runs
  //   against ONE `php artisan serve`, which answers a single request at a time (on Windows it
  //   cannot fork workers at all), so extra browsers queue rather than parallelise anyway.
  //
  // Fixing the first two by isolation would mean a reserved account per combo and a private
  // database per worker — a lot of machinery to speed up a suite the API already serialises.
  fullyParallel: false,
  workers: 1,
  // The catalog headline flow (login → browse → detail → two axe scans) is heavy and can brush
  // past the 30s default, so give every test a roomier ceiling; the multi-role headline smokes
  // raise their own further still.
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
