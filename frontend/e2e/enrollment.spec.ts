import { test, expect } from '@playwright/test'
import type { Page, TestInfo } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

// The Phase 3 headline smoke — the whole enrollment loop in one flow, against the real
// stack (needs Postgres + the Catalog, Admissions and Enrollment seeders). It drives the
// four personas the phase revolves around through the seeded world (spec §Seeder, ticket
// 11): a Student Affairs admin opens a Fall Offering, a seeded Student builds and submits a
// registration cart, their Advisor trims a line and approves it (minting Enrollments), the
// Student sees the approved schedule and withdraws from a running Spring course, and the
// assigned Instructor cancels a session and marks the register until the audit-at-risk flag
// appears. `@axe-core/playwright` runs on every new screen; per-step screenshots are
// attached to the HTML report CI uploads.

// Seeded logins (all use the shared dev password). The seeder reserves the
// student.*@seed.ejust.test namespace for exactly this smoke (spec §Seeder).
const ADMIN = 'r05@ejust.test' // Student Affairs — holds calendar.manage
const STUDENT = 'student.smoke@seed.ejust.test' // R02, advised by Dr. Amina, no Fall request yet
const ADVISOR = 'advisor.amina@ejust.test' // R03 — the Student's advisor
const INSTRUCTOR = 'r04@ejust.test' // R04 — teaches the Student's audited Spring offering

async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/$/)
}

async function logout(page: Page) {
  await page.getByRole('button', { name: 'Account menu' }).click()
  await page.getByRole('menuitem', { name: 'Log out' }).click()
  await expect(page).toHaveURL(/\/login$/)
}

// WCAG AA gate (design-system.md §12): axe on the real screens, no serious/critical.
async function expectNoSeriousA11y(page: Page) {
  const { violations } = await new AxeBuilder({ page }).analyze()
  const blocking = violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')
  expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([])
}

// Fill a MUI Select (rendered as a combobox) by its label, choosing the exactly-named option.
async function selectOption(page: Page, label: string, option: string) {
  await page.getByRole('combobox', { name: label }).click()
  await page.getByRole('option', { name: option, exact: true }).click()
}

// Choose a MUI Select option by pattern (for the "{code} — {name}" course option, whose
// em-dash makes an exact string brittle).
async function selectOptionByPattern(page: Page, label: string, pattern: RegExp) {
  await page.getByRole('combobox', { name: label }).click()
  await page.getByRole('option', { name: pattern }).first().click()
}

// A per-step screenshot, attached to the HTML report CI uploads (ticket 11 artifacts).
async function capture(page: Page, testInfo: TestInfo, name: string) {
  await testInfo.attach(name, {
    body: await page.screenshot({ fullPage: true }),
    contentType: 'image/png',
  })
}

test('headline: an admin, a student, their advisor and an instructor run the enrollment loop', async ({
  page,
}, testInfo) => {
  // The flow spans four sign-ins and five axe scans across the real stack; give it room,
  // but cap each action so a wrong locator fails fast rather than eating the whole budget.
  test.setTimeout(300_000)
  page.setDefaultTimeout(20_000)

  // ── 1. Student Affairs opens the Fall semester and creates a new Offering ─────────
  await login(page, ADMIN)
  await page.getByRole('link', { name: 'Semesters' }).first().click()
  await expect(page).toHaveURL(/\/admin\/semesters$/)
  await expectNoSeriousA11y(page)

  // Open the Fall 2026 semester's offerings (its registration window is open now).
  await page
    .getByRole('row', { name: /Fall 2026/ })
    .getByRole('link', { name: 'Offerings' })
    .click()
  await expect(page).toHaveURL(/\/admin\/semesters\/\d+\/offerings$/)
  await expect(page.getByRole('heading', { name: /^Offerings — / })).toBeVisible()
  await expectNoSeriousA11y(page)

  // Create a Fall offering for CSE532 — the one anchor course the seeder deliberately
  // leaves un-offered in Fall, so this create is idempotent against a fresh seed.
  await page.getByRole('button', { name: 'New offering' }).click()
  await selectOptionByPattern(page, 'Course', /^CSE532\b/)
  await selectOption(page, 'Instructor', 'Dr. Hoda Mansour')
  await page.getByLabel('Capacity').fill('25')
  await selectOption(page, 'Day', 'Thursday')
  await page.getByLabel('Start').fill('12:00')
  await page.getByLabel('End').fill('14:00')
  await page.getByRole('button', { name: 'Save' }).click()

  // The new offering lands in the semester's table.
  await expect(page.getByRole('cell', { name: /CSE532/ })).toBeVisible()
  await capture(page, testInfo, '1-admin-created-fall-offering')
  await logout(page)

  // ── 2. The Student builds a cart and submits it to their advisor ──────────────────
  await login(page, STUDENT)
  await page.getByRole('link', { name: 'My registration' }).first().click()
  await expect(page).toHaveURL(/\/registration$/)
  await expect(page.getByRole('heading', { name: 'Course registration' })).toBeVisible()

  // The eligible list is grouped by Enrollment Classification.
  await expect(page.getByRole('heading', { name: 'Program core' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Internal electives' })).toBeVisible()
  await expectNoSeriousA11y(page)

  // Add three courses (9 CH). The advisor will trim one below, leaving a legal 6-CH load.
  await page.getByRole('button', { name: 'Add CSE511 to your cart' }).click()
  await page.getByRole('button', { name: 'Add CSE512 to your cart' }).click()
  await page.getByRole('button', { name: 'Add CSE531 to your cart' }).click()

  // The live credit-hour total reflects the cart (ADR-0014 client mirror).
  await expect(page.getByText(/^Total: 9 credit hours?$/)).toBeVisible()

  await page.getByRole('button', { name: 'Submit to advisor' }).click()

  // The now-Pending request replaces the cart with its read-only view.
  await expect(page.getByRole('heading', { name: 'Your submitted registration' })).toBeVisible()
  await expect(page.getByText('Pending advisor review')).toBeVisible()
  await capture(page, testInfo, '2-student-submitted-cart')
  await logout(page)

  // ── 3. The Advisor trims a line and approves — minting the Enrollments ────────────
  await login(page, ADVISOR)
  await page.getByRole('link', { name: 'Registration requests' }).first().click()
  await expect(page).toHaveURL(/\/advising$/)
  await expectNoSeriousA11y(page)

  // Open the Student's request (the queue also holds the seeded Pending one).
  await page
    .getByRole('row', { name: /Layla Samir/ })
    .getByRole('link', { name: 'Review' })
    .click()
  await expect(page).toHaveURL(/\/advising\/\d+$/)
  await expect(page.getByRole('heading', { name: 'Review registration' })).toBeVisible()
  await expectNoSeriousA11y(page)

  // Trim the internal-elective line (CSE531) to Reject; the two core lines stay approved
  // (6 CH — a legal load the re-validation accepts), then record the decision.
  await page
    .getByRole('row', { name: /CSE531/ })
    .getByRole('combobox')
    .click()
  await page.getByRole('option', { name: 'Reject', exact: true }).click()
  await capture(page, testInfo, '3-advisor-trimmed-line')
  await page.getByRole('button', { name: 'Record decision' }).click()

  // Recording the decision returns the advisor to their queue.
  await expect(page).toHaveURL(/\/advising$/)
  await logout(page)

  // ── 4. The Student sees the approved schedule and withdraws a running Spring course ─
  await login(page, STUDENT)
  await page.getByRole('link', { name: 'My schedule' }).first().click()
  await expect(page).toHaveURL(/\/schedule$/)
  await expect(page.getByRole('heading', { name: 'My schedule' })).toBeVisible()

  // The approved Fall cores are now on the schedule.
  await expect(page.getByRole('cell', { name: /CSE512/ }).first()).toBeVisible()
  await expectNoSeriousA11y(page)

  // Withdraw from the running Spring elective (CSE531) — self-serve, confirmed.
  await page.getByRole('button', { name: 'Withdraw from CSE531?' }).click()
  await page.getByRole('button', { name: 'Confirm' }).click()

  // The permanent W shows on the schedule (the course status label is "Withdrawn").
  await expect(page.getByRole('row', { name: /CSE531/ }).getByText('Withdrawn')).toBeVisible()
  await capture(page, testInfo, '4-student-withdrew-spring-course')
  await logout(page)

  // ── 5. The Instructor cancels a session and marks the register until at-risk ──────
  await login(page, INSTRUCTOR)
  await page.getByRole('link', { name: 'My teaching' }).first().click()
  await expect(page).toHaveURL(/\/offerings\/mine$/)
  await expectNoSeriousA11y(page)

  // Open the register of the Spring offering the Student audits (CSE512, taught by r04).
  await page
    .getByRole('row', { name: /CSE512/ })
    .getByRole('link', { name: 'Attendance register' })
    .click()
  await expect(page).toHaveURL(/\/offerings\/\d+\/register$/)
  await expect(page.getByRole('heading', { name: 'Attendance register' })).toBeVisible()
  await expectNoSeriousA11y(page)

  // The audited Student starts with no attendance recorded (no chip yet).
  await expect(page.getByText('At risk')).toHaveCount(0)

  // Mark the Student absent on the first two sessions (staged), then cancel a later
  // session (a cancelled session counts in no denominator), then save the register.
  const absentToggles = page.getByRole('button', { name: /^Mark .+ absent on / })
  await absentToggles.nth(0).click()
  await absentToggles.nth(1).click()

  await page
    .getByRole('button', { name: /^Cancel the session on / })
    .last()
    .click()
  await expect(page.getByRole('button', { name: /^Reinstate the session on / })).toBeVisible()

  await page.getByRole('button', { name: 'Save register' }).click()

  // The audit-at-risk chip appears once the derived percentage crosses below 75%.
  await expect(page.getByText('At risk')).toBeVisible()
  await capture(page, testInfo, '5-instructor-marked-audit-at-risk')
})
