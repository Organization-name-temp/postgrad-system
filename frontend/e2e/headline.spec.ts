import { test, expect } from '@playwright/test'
import type { Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

// The Phase 0 headline smoke — the whole stack in one flow (needs Postgres):
// auth (04) + RBAC (05) + bilingual i18n (06) tied together in the shell.

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

test('headline: sign in, see identity/roles/permissions, flip to RTL, sign out', async ({
  page,
}) => {
  await login(page, 'r07@ejust.test') // Dean — holds catalog.manage, so the gated widget shows

  // Land on the guarded dashboard. Force a deterministic start language
  // (self-heals if a prior run left this seeded user in Arabic).
  await page.getByRole('button', { name: 'English' }).click()
  await expect(page).toHaveURL(/\/$/)

  // Identity, roles, and effective permissions are shown.
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible()
  await expect(page.getByText('r07@ejust.test')).toBeVisible()
  await expect(page.getByText(/R07/)).toBeVisible()
  await expect(page.getByText('catalog.manage')).toBeVisible()
  // The permission-gated widget renders with data from its gated endpoint.
  await expect(page.getByText('You can manage programs and courses.')).toBeVisible()
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')

  // The re-skinned dashboard + app shell hold WCAG AA.
  await expectNoSeriousA11y(page)

  // Toggle EN → AR: the entire layout flips to RTL with no reload.
  await page.getByRole('button', { name: 'العربية' }).click()
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
  await expect(page.getByRole('heading', { name: 'لوحة التحكم' })).toBeVisible()

  // Reset to English (leave the seeded user clean), then sign out.
  await page.getByRole('button', { name: 'English' }).click()
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')

  // Log out lives in the app-bar account menu now (Phase 0.5 shell).
  await page.getByRole('button', { name: 'Account menu' }).click()
  await page.getByRole('menuitem', { name: 'Log out' }).click()
  await expect(page).toHaveURL(/\/login$/)
})
