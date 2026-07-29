import { test, expect } from '@playwright/test'
import type { Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

// The Phase 4 grading screens against the real stack (needs Postgres + the Catalog,
// Admissions and Enrollment seeders). Ticket 01's a11y gate: the two new Instructor screens —
// the grade-sheet list and the sheet editor — are scanned with `@axe-core/playwright` in all
// four combos (light/dark × EN/AR), the style-gallery precedent, because a table of numeric
// inputs and a live letter preview is exactly where a labelling or contrast regression hides.
//
// The phase's multi-role headline smoke (enter marks → sign → finalize → the Student sees
// their GPA) joins this file with ticket 10, once there is a Phase-4 seeder to drive it.

// Seeded login (the shared dev password). r04 teaches the Spring CSE512 offering the smoke
// student audits — no gradable line — and the Fall CSE511 offering Adam takes for credit,
// which is the one with a line to mark.
const INSTRUCTOR = 'r04@ejust.test'
const GRADABLE_COURSE = /CSE511/
// The enrollment.spec.ts seeded Student — no Grade Sheet has been finalized for them yet
// (ticket 04 ships no seeder of its own; ticket 10's extends this one with a completed
// Semester, so My Grades' *populated* Semester-section state joins this scan then). Today
// this only reaches the null-CGPA "no grades yet" state — still the exact state the ticket
// requires never look catastrophic, so it is worth scanning now rather than waiting.
const STUDENT = 'student.smoke@seed.ejust.test'

const COMBOS = [
  { scheme: 'light', lang: 'en', dir: 'ltr' },
  { scheme: 'dark', lang: 'en', dir: 'ltr' },
  { scheme: 'light', lang: 'ar', dir: 'rtl' },
  { scheme: 'dark', lang: 'ar', dir: 'rtl' },
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

test.describe('grading screens', () => {
  for (const { scheme, lang, dir } of COMBOS) {
    test(`the grade sheet list and editor pass axe in ${scheme} / ${lang}`, async ({
      page,
    }, testInfo) => {
      // Two page loads and two axe scans behind a sign-in; roomier than the default, but
      // each action is capped so a wrong locator fails fast.
      test.setTimeout(120_000)
      page.setDefaultTimeout(20_000)

      // Pre-set the colour scheme (the pre-paint script reads it) before the first paint.
      await page.addInitScript((s) => localStorage.setItem('mui-mode', s), scheme)
      await login(page, INSTRUCTOR)
      await page.getByRole('button', { name: lang === 'ar' ? 'العربية' : 'English' }).click()
      await expect(page.locator('html')).toHaveAttribute('dir', dir)
      await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)

      // ── The grade-sheet list ────────────────────────────────────────────────────────
      await page.goto('/grading')
      // Wait for the seeded row before scanning, so axe sees the loaded table rather than
      // its loading spinner.
      const row = page.getByRole('row', { name: GRADABLE_COURSE })
      await expect(row).toBeVisible()
      await expectNoSeriousA11y(page, `grade sheet list — ${scheme}/${lang}`)
      await testInfo.attach(`grading-list-${scheme}-${lang}`, {
        body: await page.screenshot({ fullPage: true }),
        contentType: 'image/png',
      })

      // ── The sheet editor ────────────────────────────────────────────────────────────
      await row.getByRole('link').click()
      await expect(page).toHaveURL(/\/grading\/offerings\/\d+$/)

      // One labelled numeric input per gradable line — the shape the axe scan is really for.
      const marks = page.getByRole('spinbutton')
      await expect(marks.first()).toBeVisible()
      await expectNoSeriousA11y(page, `grade sheet editor — ${scheme}/${lang}`)

      // Typing a mark previews its letter live, client-side (ADR-0014/0021), and the
      // previewed row must stay accessible with its error/preview state rendered.
      await marks.first().fill('91')
      await expect(page.getByText('A', { exact: true })).toBeVisible()
      await marks.first().fill('120')
      await expectNoSeriousA11y(page, `grade sheet editor, invalid mark — ${scheme}/${lang}`)

      await testInfo.attach(`grading-sheet-${scheme}-${lang}`, {
        body: await page.screenshot({ fullPage: true }),
        contentType: 'image/png',
      })
    })
  }
})

test.describe('my grades screen (ticket 04)', () => {
  for (const { scheme, lang, dir } of COMBOS) {
    test(`My Grades passes axe in ${scheme} / ${lang}`, async ({ page }) => {
      page.setDefaultTimeout(20_000)

      await page.addInitScript((s) => localStorage.setItem('mui-mode', s), scheme)
      await login(page, STUDENT)
      await page.getByRole('button', { name: lang === 'ar' ? 'العربية' : 'English' }).click()
      await expect(page.locator('html')).toHaveAttribute('dir', dir)
      await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)

      await page.goto('/grading/my-grades')
      // The null-CGPA "no grades yet" state (spec: never render 0.00 for a fresh record) —
      // the only state reachable until ticket 10's seeder finalizes a Sheet for this Student.
      await expect(page.getByText('—')).toBeVisible()
      await expectNoSeriousA11y(page, `my grades — ${scheme}/${lang}`)
    })
  }
})
