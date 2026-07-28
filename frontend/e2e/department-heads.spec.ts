import { test, expect } from '@playwright/test'
import type { Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

// The Department-Heads admin screen against the real stack (needs Postgres + the
// seeders). Phase 4 ticket 02's a11y gate: the screen, its assign dialog and the open
// scope listbox are scanned with `@axe-core/playwright` in all four combos (light/dark ×
// EN/AR), the style-gallery precedent — a warning list of uncovered scopes and a select
// whose first option is a named scope rather than an entity are exactly where a
// labelling or contrast regression hides.
//
// The scan is deliberately **read-only**: the four combos share one dev database, so a
// spec that assigned a Head would race its siblings against the (Head, scope) unique
// constraint and leave rows behind. The populated table is covered by
// DepartmentHeadsPage.test.tsx, and joins the seeder-driven smoke with ticket 10.

const ADMIN = 'r09@ejust.test'

// `assign` is the exact header-button label per locale. An `/assign/i` pattern would
// also match every row's "Unassign …" action, so the label is spelled out.
const COMBOS = [
  { scheme: 'light', lang: 'en', dir: 'ltr', assign: 'Assign a Department Head' },
  { scheme: 'dark', lang: 'en', dir: 'ltr', assign: 'Assign a Department Head' },
  { scheme: 'light', lang: 'ar', dir: 'rtl', assign: 'إسناد رئيس قسم' },
  { scheme: 'dark', lang: 'ar', dir: 'rtl', assign: 'إسناد رئيس قسم' },
] as const

async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/$/)
}

// WCAG AA gate (design-system.md §12): axe on the real screens, no serious/critical.
async function expectNoSeriousA11y(page: Page, label: string) {
  const { violations } = await new AxeBuilder({ page }).analyze()
  const blocking = violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')
  expect(
    blocking,
    `${label}\n${JSON.stringify(
      blocking.map((v) => ({ id: v.id, help: v.help, nodes: v.nodes.length })),
      null,
      2,
    )}`,
  ).toEqual([])
}

test.describe('department head assignments', () => {
  for (const { scheme, lang, dir, assign } of COMBOS) {
    test(`the Department-Heads screen passes axe in ${scheme} / ${lang}`, async ({
      page,
    }, testInfo) => {
      // A sign-in, a page load and two axe scans; roomier than the default, but each
      // action is capped so a wrong locator fails fast.
      test.setTimeout(120_000)
      page.setDefaultTimeout(20_000)

      // Pre-set the colour scheme (the pre-paint script reads it) before the first paint.
      await page.addInitScript((s) => localStorage.setItem('mui-mode', s), scheme)
      await login(page, ADMIN)
      await page.getByRole('button', { name: lang === 'ar' ? 'العربية' : 'English' }).click()
      await expect(page.locator('html')).toHaveAttribute('dir', dir)
      await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)

      await page.goto('/admin/department-heads')

      // Wait for the list to settle — either assignments or the empty state — so axe
      // scans the loaded screen rather than its spinner.
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
      await expect(page.getByRole('progressbar')).toHaveCount(0)
      await expectNoSeriousA11y(page, `department heads — ${scheme}/${lang}`)
      await testInfo.attach(`department-heads-${scheme}-${lang}`, {
        body: await page.screenshot({ fullPage: true }),
        contentType: 'image/png',
      })

      // The assign dialog: two labelled selects, one of whose options is the named
      // university-wide scope rather than a Department.
      await page.getByRole('button', { name: assign, exact: true }).click()
      const dialog = page.getByRole('dialog')
      await expect(dialog).toBeVisible()
      await expect(dialog.getByRole('combobox')).toHaveCount(2)
      await expectNoSeriousA11y(page, `department heads dialog — ${scheme}/${lang}`)

      // Then the scope listbox **open**. This is the ticket's genuinely new widget — the
      // university-wide scope sits among the Departments as a peer option — and its
      // options only exist in the DOM while it is open, so a closed scan would never see
      // them. It is also where a non-option child (a divider) would break
      // `aria-required-children`, which is a critical violation.
      await dialog.getByRole('combobox').last().click()
      const options = page.getByRole('option')
      await expect(options.first()).toBeVisible()
      await expectNoSeriousA11y(page, `department heads scope listbox — ${scheme}/${lang}`)
    })
  }
})
