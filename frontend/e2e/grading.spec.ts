import {
  loginInEnglish as loginWithSavedPreference,
  selectAuthenticatedLanguage,
} from './authenticatedLanguage'
import { execSync } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { test, expect } from '@playwright/test'
import type { Page, TestInfo } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

// The Phase 4 grading screens against the real stack (needs Postgres + all four seeders).
// Ticket 01's a11y gate: the two new Instructor screens — the grade-sheet list and the sheet
// editor — are scanned with `@axe-core/playwright` in all four combos (light/dark × EN/AR),
// the style-gallery precedent, because a table of numeric inputs and a live letter preview is
// exactly where a labelling or contrast regression hides. Ticket 05 adds the sheet editor's
// Incomplete-declare fields (still the same table, a second control state worth its own scan),
// the Instructor's outstanding-Incompletes list and the resolution round it opens — both
// reachable from ticket 10's seeder, which declares real Incompletes against a seeded
// Instructor.
//
// Ticket 09 adds Student Affairs' central entry: the *same* two screens reached by a user who
// teaches nothing, so the picker is the all-offerings list and the editor carries no signature.
//
// Ticket 10 adds the phase's **multi-role headline smoke** at the bottom of this file, plus the
// axe scans that only its flow can reach: the Department Head's queue and one Sheet's review,
// and My Grades in its populated state. The null-CGPA My Grades scan stays below on its own
// Student, because "a fresh record must not look catastrophic" is its own thing to protect.

// Seeded login (the shared dev password). r04 teaches the Spring 2026 CSE512 offering the
// phase-3 smoke student audits — no gradable line — the Fall 2026 CSE511 offering Adam takes
// for credit, which is the one with a line to mark, and (from ticket 10's seeder) the completed
// Spring 2025 CSE531 the headline smoke drives.
const INSTRUCTOR = 'r04@ejust.test'
const GRADABLE_COURSE = /CSE511/
// The Instructor the ticket-10 seeder leaves holding outstanding Incompletes — declared on her
// own finalized Sheets, one overdue and one not — so the list and the resolution round it opens
// are both scannable in their real, populated state. The Student named here is the one whose
// Incomplete is **not** overdue, so nothing any other test does can settle it.
const DEFERRING_INSTRUCTOR = 'interviewer.hoda@ejust.test'
const DEFERRED_BY_INSTRUCTOR = /Dalia Wagih/
// The enrollment.spec.ts seeded Student: Enrollments, and not one finalized Grade — which is
// exactly the null-CGPA case (ticket 10's seeder deliberately leaves them that way).
const STUDENT = 'student.smoke@seed.ejust.test'
// Student Affairs — holds grades.enter without grades.finalize, so /grading is their central
// entry picker over every offering rather than "the courses you teach" (ticket 09).
const STUDENT_AFFAIRS = 'r05@ejust.test'

const COMBOS = [
  { scheme: 'light', lang: 'en', dir: 'ltr' },
  { scheme: 'dark', lang: 'en', dir: 'ltr' },
  { scheme: 'light', lang: 'ar', dir: 'rtl' },
  { scheme: 'dark', lang: 'ar', dir: 'rtl' },
] as const

// Bilingual copy the ticket-05 assertions below need to match — the rest of this file avoids
// the problem entirely by only asserting language-invariant content (course codes, letters).
const COPY = {
  en: {
    declareIncomplete: /mark incomplete instead/i,
    enterMarkInstead: /enter a mark instead/i,
    coursework: /coursework completed/i,
    resolve: /^resolve$/i,
    sign: /sign and submit for review/i,
  },
  ar: {
    declareIncomplete: /تسجيل "غير مكتمل"/,
    enterMarkInstead: /إدخال درجة بدلًا من ذلك/,
    coursework: /نسبة إنجاز المقرر/,
    resolve: /^تسوية$/,
    sign: /توقيع وإرسال للمراجعة/,
  },
} as const

async function login(page: Page, email: string) {
  await loginWithSavedPreference(page, email)
}

/**
 * Sign in **and pin English** — what the headline flow needs and the axe scans above do not.
 * The locale is persisted per user (`preferred_locale` on /me), and those scans sign in as some
 * of these same seeded accounts and leave Arabic behind, so a flow that asserts English copy
 * must state that rather than inherit whatever ran before it (the enrollment.spec.ts precedent).
 */
async function loginInEnglish(page: Page, email: string) {
  await login(page, email)
  await selectAuthenticatedLanguage(page, 'en')
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')
}

async function logout(page: Page) {
  await page.getByRole('button', { name: 'Account menu' }).click()
  await page.getByRole('menuitem', { name: 'Log out' }).click()
  await expect(page).toHaveURL(/\/login$/)
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

// A per-step screenshot, attached to the HTML report CI uploads (ticket 10 artifacts) rather
// than pixel-asserted.
async function capture(page: Page, testInfo: TestInfo, name: string) {
  await testInfo.attach(name, {
    body: await page.screenshot({ fullPage: true }),
    contentType: 'image/png',
  })
}

// The Incomplete→Fail-Withdrawal conversion is an artisan command, not a screen (ADR-0005: no
// scheduler in this build, so the SRS §4.7.3.3 conversion is hand-run). The smoke runs the real
// one against the same database the browser is driving, which is the only way the last step of
// the headline flow is exercised end to end rather than simulated.
const BACKEND_DIR = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  '..',
  '..',
  'backend',
)

function artisan(command: string): string {
  // Through a shell, exactly as playwright.config.ts boots `php artisan serve`: on Windows the
  // project's PHP 8.4 is a `php.bat` shim, which only a shell resolves via PATHEXT — a direct
  // spawn silently finds some other `php.exe` on PATH and dies on the platform check.
  return execSync(`php artisan ${command}`, { cwd: BACKEND_DIR, encoding: 'utf8' })
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
      await selectAuthenticatedLanguage(page, lang)
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

      // ── Declaring an Incomplete (ticket 05) ─────────────────────────────────────────
      // Toggled open and scanned, then toggled back closed without ever saving — this row
      // is shared with the invalid-mark state just scanned above, and nothing here should
      // write to the dev database.
      const copy = COPY[lang]
      await page.getByRole('button', { name: copy.declareIncomplete }).first().click()
      await expect(page.getByRole('spinbutton', { name: copy.coursework })).toBeVisible()
      await expectNoSeriousA11y(page, `grade sheet editor, incomplete fields — ${scheme}/${lang}`)
      await page.getByRole('button', { name: copy.enterMarkInstead }).click()
    })
  }
})

test.describe('outstanding incompletes (ticket 05)', () => {
  for (const { scheme, lang, dir } of COMBOS) {
    test(`the outstanding-incompletes list passes axe in ${scheme} / ${lang}`, async ({ page }) => {
      // A sign-in, two page loads and two axe scans; roomier than the default, but each
      // action is capped so a wrong locator fails fast.
      test.setTimeout(120_000)
      page.setDefaultTimeout(20_000)

      await page.addInitScript((s) => localStorage.setItem('mui-mode', s), scheme)
      // Not the headline smoke's Instructor: this list is scoped to whoever *declared* the
      // Incomplete, and the smoke's own declaration is created and then converted mid-run, so
      // scanning r04's list would race it. The seeder's deferring Instructor holds two,
      // finalized and stable — including one the conversion command never touches.
      await login(page, DEFERRING_INSTRUCTOR)
      await selectAuthenticatedLanguage(page, lang)
      await expect(page.locator('html')).toHaveAttribute('dir', dir)
      await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)

      await page.goto('/grading/incompletes')
      // The populated state: a row per outstanding Incomplete, each with its derived deadline.
      // Located by the Student rather than the course, because this Instructor holds two — and
      // the *other* one is the overdue Incomplete the headline smoke's conversion command
      // settles, so naming it would make this scan depend on test order.
      const row = page.getByRole('row', { name: DEFERRED_BY_INSTRUCTOR })
      await expect(row).toBeVisible()
      await expectNoSeriousA11y(page, `outstanding incompletes — ${scheme}/${lang}`)

      // The resolution round, reached the way an Instructor reaches it. Opening it creates the
      // round lazily server-side, keyed on the Incomplete, so the four combos share one rather
      // than each leaving a fresh Sheet behind. Nothing is entered or signed here.
      await row.getByRole('link', { name: COPY[lang].resolve }).click()
      await expect(page).toHaveURL(/\/grading\/incompletes\/\d+$/)
      await expect(page.getByRole('spinbutton').first()).toBeVisible()
      await expectNoSeriousA11y(page, `incomplete resolution — ${scheme}/${lang}`)
    })
  }
})

test.describe('central grade entry (ticket 09)', () => {
  for (const { scheme, lang, dir } of COMBOS) {
    test(`the central entry picker and sheet editor pass axe in ${scheme} / ${lang}`, async ({
      page,
    }) => {
      // Two page loads and two axe scans behind a sign-in, as the instructor scan above.
      test.setTimeout(120_000)
      page.setDefaultTimeout(20_000)

      await page.addInitScript((s) => localStorage.setItem('mui-mode', s), scheme)
      await login(page, STUDENT_AFFAIRS)
      await selectAuthenticatedLanguage(page, lang)
      await expect(page.locator('html')).toHaveAttribute('dir', dir)
      await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)

      // ── The picker over every offering ──────────────────────────────────────────────
      await page.goto('/grading')
      // r05 teaches nothing, so a row at all is proof the list is the *central* one — the
      // instructor-scoped list would be empty for this user. Both seeded CSE511 offerings
      // (Spring and Fall) appear here, which is the point; either has a gradable line, so the
      // scan takes the first.
      const row = page.getByRole('row', { name: GRADABLE_COURSE }).first()
      await expect(row).toBeVisible()
      await expectNoSeriousA11y(page, `central entry picker — ${scheme}/${lang}`)

      // ── The same sheet editor, without a signature ──────────────────────────────────
      await row.getByRole('link').click()
      await expect(page).toHaveURL(/\/grading\/offerings\/\d+$/)
      const marks = page.getByRole('spinbutton')
      await expect(marks.first()).toBeVisible()
      await expect(page.getByRole('button', { name: COPY[lang].sign })).toHaveCount(0)
      await expectNoSeriousA11y(page, `central entry sheet editor — ${scheme}/${lang}`)
    })
  }
})

test.describe('my grades screen (ticket 04)', () => {
  for (const { scheme, lang, dir } of COMBOS) {
    test(`My Grades passes axe in ${scheme} / ${lang}`, async ({ page }) => {
      page.setDefaultTimeout(20_000)

      await page.addInitScript((s) => localStorage.setItem('mui-mode', s), scheme)
      await login(page, STUDENT)
      await selectAuthenticatedLanguage(page, lang)
      await expect(page.locator('html')).toHaveAttribute('dir', dir)
      await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)

      await page.goto('/grading/my-grades')
      // The null-CGPA "no grades yet" state (spec: never render 0.00 for a fresh record). The
      // populated state is scanned by the headline smoke below, on a Student whose grades it
      // finalizes itself.
      await expect(page.getByText('—')).toBeVisible()
      await expectNoSeriousA11y(page, `my grades — ${scheme}/${lang}`)
    })
  }
})

// ── The Phase-4 headline smoke (ticket 10) ────────────────────────────────────────────────
//
// The whole grading loop in one flow, across four sign-ins and the real stack: the Instructor
// grades one Student and defers another, signs; the Department Head reviews the marks and the
// stated Incomplete reason, and finalizes; the graded Student sees their letter, their Semester
// GPA and their CGPA; the deferred Student sees the Incomplete with its deadline, the
// conversion command runs, and the same page now reads Fail Withdrawal.
//
// It drives the ticket-10 seeder's **completed Spring 2025** — the only term where grades are
// both enterable and old enough that an Incomplete declared today is already past its derived
// week-10 deadline, so the conversion has something real to convert on seed day.

const HEADLINE_COURSE = /CSE531/
const DEPARTMENT_HEAD = 'r06@ejust.test' // R06, assigned to Computer Engineering by the seeder
const GRADED_STUDENT = 'student.graded@seed.ejust.test' // one finalized prep A, and this course
const DEFERRED_STUDENT = 'student.deferred@seed.ejust.test' // the one the Instructor defers

test('headline: an instructor grades and defers, the head finalizes, and the students see it', async ({
  page,
}, testInfo) => {
  // Four sign-ins, an artisan command and three axe scans across the real stack; give it room,
  // but cap each action so a wrong locator fails fast rather than eating the whole budget.
  test.setTimeout(300_000)
  page.setDefaultTimeout(20_000)

  // ── 1. The Instructor enters a mark, declares an Incomplete, and signs ────────────
  await loginInEnglish(page, INSTRUCTOR)
  await page.getByRole('link', { name: 'Grade sheets' }).first().click()
  await expect(page).toHaveURL(/\/grading$/)

  // The seeded Draft round on the completed Spring 2025 offering — the only CSE531 this
  // Instructor teaches in any Semester, so the row is unambiguous.
  const sheetRow = page.getByRole('row', { name: HEADLINE_COURSE })
  await expect(sheetRow).toBeVisible()
  await sheetRow.getByRole('link', { name: 'Open grade sheet' }).click()
  await expect(page).toHaveURL(/\/grading\/offerings\/\d+$/)

  // One final percentage, committed on blur — the letter and its points are derived, never
  // typed (ADR-0021's scale, mirrored client-side for the preview).
  const nourMark = page.getByRole('spinbutton', { name: 'Mark for Nour Adel' })
  await nourMark.fill('78')
  await nourMark.blur()
  const nourRow = page.getByRole('row', { name: /Nour Adel/ })
  await expect(nourRow.getByText('C+', { exact: true })).toBeVisible()
  await expect(nourRow.getByText('Recorded')).toBeVisible()

  // The other Student is deferred rather than failed: a completion percentage above the
  // configured 70% and a reason, saved as one explicit act (SRS §4.7.3.1).
  const karimRow = page.getByRole('row', { name: /Karim Fouad/ })
  await karimRow.getByRole('button', { name: 'Mark Incomplete instead' }).click()
  await karimRow.getByRole('spinbutton', { name: 'Coursework completed (%)' }).fill('80')
  await karimRow
    .getByRole('textbox', { name: 'Reason' })
    .fill('Fieldwork data collection was cut short; the final report is outstanding.')
  await karimRow.getByRole('button', { name: 'Save', exact: true }).click()
  await expect(karimRow.getByText('Incomplete declared')).toBeVisible()

  // A declared Incomplete is decided, not blank — so the whole course can now be signed as one
  // act, and the round becomes read-only to everyone including its own author.
  await expect(page.getByText(/ready to sign/i)).toBeVisible()
  await page.getByRole('button', { name: 'Sign and submit for review' }).click()
  await expect(page.getByText('Awaiting review')).toBeVisible()
  await capture(page, testInfo, '1-instructor-signed-the-sheet')
  await logout(page)

  // ── 2. The Department Head reviews and finalizes ─────────────────────────────────
  await loginInEnglish(page, DEPARTMENT_HEAD)
  await page.getByRole('link', { name: 'Grade review' }).first().click()
  await expect(page).toHaveURL(/\/grading\/review$/)

  // Wait for the queue row before scanning, so axe sees the loaded table rather than a spinner.
  const queueRow = page.getByRole('row', { name: HEADLINE_COURSE })
  await expect(queueRow).toBeVisible()
  await expectNoSeriousA11y(page, 'department head review queue')
  await queueRow.getByRole('link', { name: 'Review' }).click()
  await expect(page).toHaveURL(/\/grading\/review\/\d+$/)

  // Every mark and the Incomplete's stated reason, read-only — a Head never writes a mark, so
  // authorship stays with the Instructor who taught the course.
  await expect(page.getByRole('button', { name: 'Finalize' })).toBeVisible()
  await expect(page.getByText(/Incomplete — 80% completed/)).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Grade distribution' })).toBeVisible()
  await expect(page.getByRole('spinbutton')).toHaveCount(0)
  await expectNoSeriousA11y(page, 'department head sheet review')
  await capture(page, testInfo, '2-head-reviewing-the-sheet')

  await page.getByRole('button', { name: 'Finalize' }).click()
  await expect(page).toHaveURL(/\/grading\/review$/)
  await logout(page)

  // ── 3. The graded Student sees the letter, the Semester GPA and the CGPA ─────────
  await loginInEnglish(page, GRADED_STUDENT)
  await page.getByRole('link', { name: 'My Grades' }).first().click()
  await expect(page).toHaveURL(/\/grading\/my-grades$/)

  const gradedRow = page.getByRole('row', { name: HEADLINE_COURSE })
  await expect(gradedRow).toBeVisible()
  await expect(gradedRow.getByText('C+', { exact: true })).toBeVisible()
  await expect(gradedRow.getByText('Earned credit')).toBeVisible()

  // The CGPA as a **value**, not merely present: the seeded preparatory A (4.00 over 1 CH)
  // beside the C+ this flow just finalized (2.50 over 3 CH) is 11.50 ÷ 4 = 2.875, half-up to
  // 2.88 — so credit weighting and the rounding rule are both exercised end to end.
  await expect(page.getByText('2.88', { exact: true })).toBeVisible()
  await expect(page.getByText('Semester GPA: 2.88')).toBeVisible()
  await expectNoSeriousA11y(page, 'my grades, populated')
  await capture(page, testInfo, '3-student-sees-gpa-and-cgpa')
  await logout(page)

  // ── 4. The deferred Student, before and after the conversion ─────────────────────
  await loginInEnglish(page, DEFERRED_STUDENT)
  await page.goto('/grading/my-grades')

  // Outstanding: on the record, with its derived deadline, counting toward neither the CGPA nor
  // the earned hours.
  const deferredRow = page.getByRole('row', { name: HEADLINE_COURSE })
  await expect(deferredRow.getByText('Incomplete')).toBeVisible()
  await expect(deferredRow.getByText(/^Resolve by /)).toBeVisible()
  await capture(page, testInfo, '4-student-sees-the-incomplete')

  // The Spring 2025 Incomplete is already past its week-10 deadline the day it is declared —
  // the whole point of the seeder's three-semester calendar — so the hand-run command converts
  // it now rather than a term from now.
  expect(artisan('grading:convert-expired-incompletes')).toMatch(/Converted [1-9]\d* expired/)

  await page.reload()
  await expect(deferredRow.getByText('Fail Withdrawal')).toBeVisible()
  await capture(page, testInfo, '5-student-sees-the-fail-withdrawal')
})
