import { test, expect } from '@playwright/test'
import type { Page, TestInfo } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

// The Phase 2 headline smoke — the whole admissions pipeline in one flow, against the
// real stack (needs Postgres + the Catalog and Admissions seeders). A stranger registers
// into the open Fall 2026 cycle, completes the wizard, and is carried through every
// reviewer role — Student Affairs, the Dean, the interviewer, back to Student Affairs and
// the Dean — until they are admitted and sign in as a Student with a University ID.

// A fresh applicant per run, so re-runs against a non-reset dev DB don't collide (the
// seeder never uses these values — it reserves applicant.*@seed.ejust.test / 3000…ids).
const UNIQUE = Date.now()
const APPLICANT = {
  name: `Smoke Applicant ${UNIQUE}`,
  email: `smoke.${UNIQUE}@applicant.test`,
  password: 'password',
  nationalId: `2${String(UNIQUE).slice(-13)}`,
}

// A tiny valid PDF and PNG so the server's content-type validation passes (scan docs
// accept PDF; the personal photo must be an image).
const PDF = Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n')
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
  'base64',
)

async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/$/)
  // Pin English. The locale is persisted per user (`preferred_locale` on /me), and the bilingual
  // axe specs sign in as these same seeded accounts and leave Arabic behind — so a flow that
  // asserts English copy must state that rather than inherit whatever ran before it.
  await page.getByRole('button', { name: 'English' }).click()
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')
}

async function logout(page: Page) {
  await page.getByRole('button', { name: 'Account menu' }).click()
  await page.getByRole('menuitem', { name: 'Log out' }).click()
  await expect(page).toHaveURL(/\/login$/)
}

async function expectNoSeriousA11y(page: Page) {
  const { violations } = await new AxeBuilder({ page }).analyze()
  const blocking = violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')
  expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([])
}

// Fill a MUI Select (rendered as a combobox) by its label, choosing the named option.
async function selectOption(page: Page, label: string, option: string) {
  await page.getByRole('combobox', { name: label }).click()
  await page.getByRole('option', { name: option, exact: true }).click()
}

// A searchable MUI Autocomplete (e.g. nationality, ticket 06): open it, type to filter,
// then pick the matching option.
async function selectAutocomplete(page: Page, label: string, query: string, option: string) {
  const box = page.getByRole('combobox', { name: label })
  await box.click()
  await box.fill(query)
  await page.getByRole('option', { name: option }).first().click()
}

// A per-step screenshot, attached to the HTML report CI uploads (ticket 12 artifacts).
async function capture(page: Page, testInfo: TestInfo, name: string) {
  await testInfo.attach(name, {
    body: await page.screenshot({ fullPage: true }),
    contentType: 'image/png',
  })
}

// Open the queue row for our applicant (queues also hold seeded rows) and follow it.
async function openApplicantRow(page: Page) {
  if (['/screening', '/dean', '/dean/decisions'].includes(new URL(page.url()).pathname)) {
    await page.getByLabel('Search applicants').fill(APPLICANT.name)
  }
  const row = page.getByRole('row', { name: new RegExp(APPLICANT.name) })
  await expect(row).toBeVisible()
  await row.getByRole('link', { name: 'Open' }).click()
}

test('headline: a stranger applies and is admitted as a Student across every role', async ({
  page,
}, testInfo) => {
  // The flow spans ~nine sign-ins and the whole pipeline; give it room — but cap each
  // individual action so a wrong locator fails fast rather than eating the whole budget.
  test.setTimeout(300_000)
  page.setDefaultTimeout(20_000)

  // ── 1. Register into the open cycle ─────────────────────────────────────────────
  await page.goto('/register')
  await expect(page.getByRole('heading', { name: 'Start your application' })).toBeVisible()
  await expectNoSeriousA11y(page)

  await page.getByLabel('Full name').fill(APPLICANT.name)
  await page.getByLabel('Email').fill(APPLICANT.email)
  // Anchored regex, not exact — MUI adds a required asterisk to the accessible name,
  // and this must not also match "Confirm password".
  await page.getByLabel(/^Password/).fill(APPLICANT.password)
  await page.getByLabel('Confirm password').fill(APPLICANT.password)
  await page.getByRole('button', { name: 'Register and start' }).click()

  // Lands in the wizard at Step 1.
  await expect(page).toHaveURL(/\/apply$/)
  await expect(page.getByRole('heading', { name: 'Personal information' })).toBeVisible()

  // ── 2a. Personal info ───────────────────────────────────────────────────────────
  await page.getByLabel('Full name (English)').fill(APPLICANT.name)
  await page.getByLabel('Full name (Arabic)').fill('متقدم الاختبار')
  await selectOption(page, 'Identification type', 'National ID')
  await page.getByLabel('Identification number').fill(APPLICANT.nationalId)
  // Phone is a country-selector + national number (default Egypt, +20); type the
  // national part — the field stores canonical E.164 (ticket 06).
  await page.getByLabel('Phone', { exact: false }).fill('1000000000')
  await page.getByLabel('Date of birth').fill('1998-05-20')
  // Nationality is a searchable ISO-3166 list, stored as the alpha-2 code (ticket 06).
  await selectAutocomplete(page, 'Nationality', 'Egypt', 'Egypt')
  await selectOption(page, 'Gender', 'Male')
  // Scope by role: the phone field's "Country code" is a combobox; the address
  // "Country" is the textbox — getByLabel alone matches both (ticket 06).
  await page.getByRole('textbox', { name: 'Country' }).fill('Egypt')
  await page.getByLabel('City').fill('Alexandria')
  await page.getByLabel('Street').fill('15 Corniche Road')
  await page.getByLabel('Previous university').fill('Cairo University')
  await page.getByLabel('Graduation certificate').fill('BSc in Computer Science')
  await page.getByLabel('Graduation date').fill('2020-07-01')
  await selectOption(page, 'Previous degree', 'BSc')
  await page.getByLabel('Previous major').fill('Computer Science')
  // GPA/CGPA are disabled until a scale is chosen (ticket 07) — pick the scale first.
  await selectOption(page, 'GPA scale', '4.0')
  await page.getByLabel('Graduation GPA').fill('3.5')
  await page.getByLabel('Graduation CGPA').fill('3.45')
  await selectOption(page, 'Applying for', 'MSc')
  // "None" avoids the conditional English-certificate requirement at finalize.
  await selectOption(page, 'English proficiency test', 'None')
  await page.getByRole('button', { name: 'Save & continue' }).click()

  // ── 2b. Email verification (link shown on screen, no mail sent) ──────────────────
  // The link is a signed, unauthenticated URL; opening it (as the applicant would)
  // marks the email verified. We navigate to it directly rather than using the in-app
  // "Verify email" button, whose fetch is cross-origin under the split dev servers
  // (SPA :5173, API :8000) — the link itself is the intended verification path.
  await expect(page.getByRole('heading', { name: 'Verify your email' })).toBeVisible()
  const verifyHref = await page.getByRole('link', { name: /verify-email/ }).getAttribute('href')
  expect(verifyHref).toBeTruthy()
  await page.goto(verifyHref as string)
  await expect(page.getByText(/email_verified/)).toBeVisible()
  await page.goto('/apply')

  // ── 2c. Documents (the four required types) ──────────────────────────────────────
  await expect(page.getByRole('heading', { name: 'Documents' })).toBeVisible()
  const fileInputs = page.locator('input[type="file"]')
  await fileInputs
    .nth(0)
    .setInputFiles({ name: 'certificate.pdf', mimeType: 'application/pdf', buffer: PDF })
  await fileInputs
    .nth(1)
    .setInputFiles({ name: 'transcript.pdf', mimeType: 'application/pdf', buffer: PDF })
  await fileInputs
    .nth(2)
    .setInputFiles({ name: 'national-id.pdf', mimeType: 'application/pdf', buffer: PDF })
  await fileInputs.nth(3).setInputFiles({ name: 'photo.png', mimeType: 'image/png', buffer: PNG })

  // All four uploaded filenames appear before continuing.
  await expect(page.getByText('certificate.pdf')).toBeVisible()
  await expect(page.getByText('transcript.pdf')).toBeVisible()
  await expect(page.getByText('national-id.pdf')).toBeVisible()
  await expect(page.getByText('photo.png')).toBeVisible()
  await page.getByRole('button', { name: 'Continue to program selection' }).click()

  // ── 2d. Program selection (a qualifying MSc program) ─────────────────────────────
  await expect(page.getByRole('heading', { name: 'Choose your program' })).toBeVisible()
  const programNode = page.getByRole('treeitem', { name: /MSc in Computer Engineering/ })
  await expect(programNode).toBeVisible()
  await programNode.getByText('Primary', { exact: true }).click()
  await page.getByRole('button', { name: 'Save & continue' }).click()

  // ── 2e. Review & finalize ────────────────────────────────────────────────────────
  await expect(page.getByRole('heading', { name: 'Review & submit' })).toBeVisible()
  await page.getByRole('button', { name: 'Finalize application' }).click()

  // The wizard flips to the read-only status view: the application is in review.
  await expect(page.getByText('Your application has been submitted')).toBeVisible()
  await capture(page, testInfo, '1-applicant-finalized')
  await logout(page)

  // ── 3. Student Affairs approves for Dean review ──────────────────────────────────
  await login(page, 'r05@ejust.test')
  await page.getByRole('link', { name: 'Screening queue' }).first().click()
  await expect(page).toHaveURL(/\/screening$/)
  await openApplicantRow(page)
  await page.getByRole('button', { name: 'Approve for Dean review' }).click()
  await expect(page).toHaveURL(/\/screening$/)
  await capture(page, testInfo, '2-sa-approved')
  await logout(page)

  // ── 4. The Dean approves for interview ───────────────────────────────────────────
  await login(page, 'r07@ejust.test')
  await page.getByRole('link', { name: 'Dean review' }).first().click()
  await expect(page).toHaveURL(/\/dean$/)
  await openApplicantRow(page)
  await page.getByRole('button', { name: 'Approve for interview' }).click()
  await expect(page).toHaveURL(/\/dean$/)
  await capture(page, testInfo, '3-dean-approved-for-interview')
  await logout(page)

  // ── 5. Student Affairs schedules an interview slot ───────────────────────────────
  await login(page, 'r05@ejust.test')
  // Each run creates its own capacity-one slot; repeated runs must not exhaust
  // the named demo slots or depend on which of them was booked previously.
  await page.getByRole('link', { name: 'Interview slots', exact: true }).click()
  await page.getByRole('button', { name: 'New slot' }).click()
  const slotForm = page.getByRole('dialog', { name: 'New slot' })
  await slotForm
    .getByLabel('Date & time')
    .fill(new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 16))
  await slotForm.getByRole('combobox', { name: 'Interviewer' }).fill('Hoda')
  await page.getByRole('option', { name: 'Dr. Hoda Mansour', exact: true }).click()
  const slotLocation = `Smoke room ${UNIQUE}`
  await slotForm.getByLabel('Location').fill(slotLocation)
  await slotForm.getByRole('button', { name: 'Save', exact: true }).click()
  await expect(slotForm).not.toBeVisible()
  const replacementLocation = `Smoke replacement ${UNIQUE}`
  await page.getByRole('button', { name: 'New slot' }).click()
  await slotForm
    .getByLabel('Date & time')
    .fill(new Date(Date.now() + 4 * 86400000).toISOString().slice(0, 16))
  await slotForm.getByRole('combobox', { name: 'Interviewer' }).fill('Hoda')
  await page.getByRole('option', { name: 'Dr. Hoda Mansour', exact: true }).click()
  await slotForm.getByLabel('Location').fill(replacementLocation)
  await slotForm.getByRole('button', { name: 'Save', exact: true }).click()
  await expect(slotForm).not.toBeVisible()
  await page.getByRole('link', { name: 'Interview scheduling' }).first().click()
  await expect(page).toHaveURL(/\/interviews\/scheduling$/)
  await page
    .getByRole('region', { name: 'Awaiting scheduling' })
    .getByLabel('Search applicants')
    .fill(APPLICANT.name)
  await page
    .getByRole('row', { name: new RegExp(APPLICANT.name) })
    .getByRole('button', { name: 'Schedule' })
    .click()
  // Book the slot created for this run, assigned to the named interviewer below.
  await page.getByRole('combobox', { name: 'Interview slot' }).fill(slotLocation)
  await page.getByRole('option', { name: new RegExp(slotLocation) }).click()
  await page.getByRole('button', { name: 'Schedule interview' }).click()
  await capture(page, testInfo, '4-sa-scheduled')
  await logout(page)

  // The applicant searches their permitted open-slot lookup, uses the one
  // reschedule, then confirms attendance. Each run owns both slots and applicant.
  await login(page, APPLICANT.email)
  await page.goto('/apply')
  await page.getByRole('button', { name: 'Reschedule', exact: true }).click()
  await page.getByRole('combobox', { name: 'New slot' }).fill(replacementLocation)
  await page.getByRole('option', { name: new RegExp(replacementLocation) }).click()
  await page.getByRole('button', { name: 'Move to this slot' }).click()
  await expect(page.getByRole('button', { name: 'Reschedule', exact: true })).not.toBeVisible()
  await page.getByRole('button', { name: 'Confirm attendance' }).click()
  await expect(page.getByText('Confirmed', { exact: true })).toBeVisible()
  await expectNoSeriousA11y(page)
  await capture(page, testInfo, '4b-applicant-rescheduled')
  await logout(page)

  // ── 6. The assigned interviewer records an evaluation ────────────────────────────
  await login(page, 'interviewer.hoda@ejust.test')
  await page.getByRole('link', { name: 'My interviews' }).first().click()
  await expect(page).toHaveURL(/\/interviews\/mine$/)
  await page.getByLabel('Search applicants').fill(APPLICANT.name)
  await page
    .getByRole('row', { name: new RegExp(APPLICANT.name) })
    .getByRole('button', { name: 'Record evaluation' })
    .click()
  await page.getByLabel(/Score/).fill('8')
  await page.getByLabel('Notes').fill('Strong candidate; recommended.')
  await page.getByRole('button', { name: 'Save' }).click()
  await capture(page, testInfo, '5-interviewer-evaluated')
  await logout(page)

  // ── 7. Student Affairs marks the interview complete ──────────────────────────────
  await login(page, 'r05@ejust.test')
  await page.getByRole('link', { name: 'Interview scheduling' }).first().click()
  await page
    .getByRole('region', { name: 'Scheduled interviews' })
    .getByLabel('Search applicants')
    .fill(APPLICANT.name)
  await page
    .getByRole('row', { name: new RegExp(APPLICANT.name) })
    .getByRole('button', { name: 'Mark complete' })
    .click()
  await expect(
    page.getByRole('row', { name: new RegExp(APPLICANT.name) }).getByText('Completed'),
  ).toBeVisible()
  await capture(page, testInfo, '6-sa-completed')
  await logout(page)

  // ── 8. The Dean accepts — minting the Student (advisor pre-filled) ───────────────
  await login(page, 'r07@ejust.test')
  await page.getByRole('link', { name: 'Admission decisions' }).first().click()
  await expect(page).toHaveURL(/\/dean\/decisions$/)
  // The new decisions-queue screen holds WCAG AA.
  await expectNoSeriousA11y(page)
  await openApplicantRow(page)
  await expect(page.getByRole('heading', { name: APPLICANT.name })).toBeVisible()
  // The new decision screen holds WCAG AA.
  await expectNoSeriousA11y(page)
  await page.getByRole('button', { name: 'Accept & admit' }).click()

  // The minted University ID (format {year}-{programCode}-{NNNN}) is surfaced.
  await expect(page.getByText(/Applicant admitted/)).toBeVisible()
  await expect(page.getByText(/\d{4}-MSC-CE-\d{4}/)).toBeVisible()
  await capture(page, testInfo, '7-dean-accepted')
  await logout(page)

  // ── 9. The admitted applicant signs in as a Student and sees their University ID ─
  await login(page, APPLICANT.email)
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible()
  await expect(page.getByText('University ID')).toBeVisible()
  await expect(page.getByText(/\d{4}-MSC-CE-\d{4}/)).toBeVisible()
  // The role swapped applicant → Student (R02).
  await expect(page.getByText(/R02 · Student/)).toBeVisible()
  await expectNoSeriousA11y(page)
  await capture(page, testInfo, '8-student-dashboard')
})
