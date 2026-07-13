import { test, expect } from '@playwright/test'
import type { Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

// The Phase 1 headline smoke — the whole stack in one flow (needs Postgres + the
// CatalogSeeder): auth + RBAC + bilingual i18n + the catalog browse tree and Course
// detail, tied together in the app shell.

async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
}

// WCAG AA gate (design-system.md §12): axe on the real screens, no serious/critical.
async function expectNoSeriousA11y(page: Page) {
  const { violations } = await new AxeBuilder({ page }).analyze()
  const blocking = violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')
  expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([])
}

test('an unauthenticated visit to a guarded route redirects to login', async ({ page }) => {
  await page.goto('/')

  await expect(page).toHaveURL(/\/login$/)
  await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible()
  // The login page reaches the API through the Vite proxy (walking-skeleton proof).
  await expect(page.getByText(/API status:\s*ok/i)).toBeVisible()

  // The re-skinned login screen holds WCAG AA.
  await expectNoSeriousA11y(page)
})

test('headline: sign in, browse the catalog tree, open a course, flip to RTL, sign out', async ({
  page,
}) => {
  await login(page, 'r07@ejust.test') // Dean — holds catalog.view

  // Land on the guarded dashboard. Force a deterministic start language
  // (self-heals if a prior run left this seeded user in Arabic).
  await page.getByRole('button', { name: 'English' }).click()
  await expect(page).toHaveURL(/\/$/)

  // Identity, roles, and effective permissions are shown (Phase 0 proof, retained).
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible()
  await expect(page.getByText('r07@ejust.test')).toBeVisible()
  await expect(page.getByText('catalog.view')).toBeVisible()

  // Open the browse catalog from the side nav.
  await page.getByRole('link', { name: 'Browse catalog' }).first().click()
  await expect(page).toHaveURL(/\/catalog$/)

  // The tree renders grouped by Faculty under the synthetic University root (seeder).
  await expect(page.getByText('Egypt-Japan University of Science and Technology')).toBeVisible()
  await expect(page.getByText('Faculty of Engineering')).toBeVisible()

  // Expand a Faculty node — its Department drills into view (expand/collapse works).
  await page.getByText('Faculty of Engineering').click()
  await expect(page.getByText('Computer Engineering')).toBeVisible()

  // Client-side search then narrows to a single Course, which we open.
  await page.getByLabel('Search programs and courses').fill('CSE511')
  const courseNode = page.getByText(/CSE511 —/)
  await expect(courseNode).toBeVisible()

  // The tree screen holds WCAG AA.
  await expectNoSeriousA11y(page)

  await courseNode.click()
  await expect(page).toHaveURL(/\/catalog\/courses\/\d+$/)

  // Course detail: heading + the "Appears in" placements section.
  await expect(page.getByRole('heading', { name: 'Advanced Algorithms' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Appears in' })).toBeVisible()

  // The detail screen holds WCAG AA.
  await expectNoSeriousA11y(page)

  // Toggle EN → AR: the layout flips to RTL and the Course name shows in Arabic,
  // with no reload.
  await page.getByRole('button', { name: 'العربية' }).click()
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
  await expect(page.getByRole('heading', { name: 'خوارزميات متقدمة' })).toBeVisible()

  // Reset to English (leave the seeded user clean), then sign out.
  await page.getByRole('button', { name: 'English' }).click()
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')

  await page.getByRole('button', { name: 'Account menu' }).click()
  await page.getByRole('menuitem', { name: 'Log out' }).click()
  await expect(page).toHaveURL(/\/login$/)
})
