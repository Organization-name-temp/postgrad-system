import { test, expect } from '@playwright/test'
import type { Page, TestInfo } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

// The Phase 4.5 headline smoke: the Dashboard (ADR-0024) against the real stack, signed in as
// seven seeded accounts in turn. It asserts, for each, **exactly which Cards render and which do
// not** — absence as firmly as presence, because the risk this phase carries is not "does a count
// render", it is *does the right person see the right Cards*, which nothing else in the suite
// covers end to end.
//
// This is also the only seam where the **server gate and the client composition are proved to
// agree**. `DashboardPage.test.tsx` mocks the auth hook and therefore tests the mirror; the Pest
// feature tests prove the authority. Only a real login exercises both at once, and ADR-0014 makes
// the client an explicit mirror of server rules — verifying a mirror means testing both faces.
//
// The single most important assertion in the phase is `multi@`: one account, two Roles, its
// advisee queue *and* its ungraded Sheets on one page, with no role switcher anywhere on screen.
//
// Needs Postgres and a fresh seed (`php artisan migrate:fresh --seed --force`) — every count and
// row asserted below is seeded data (phase 4.5, ticket 02), so a seeder regression fails this test
// rather than silently changing a demo. It reads only: no Card endpoint mutates anything, so the
// spec is re-runnable and disturbs no other suite's world.

// The twelve Cards by their English headings, which is what the Dashboard renders them as. Keeping
// the whole inventory in one table is what lets every test below assert the *complement* of its
// own set for free — the absence assertions are generated, never hand-listed and never forgotten.
const CARDS = {
  myApplication: 'My application',
  screeningQueue: 'Screening queue',
  admissionDecisions: 'Admission decisions',
  myRegistration: 'My registration',
  adviseeRequests: 'Advisee requests',
  myGradeSheets: 'My grade sheets',
  sheetsAwaitingReview: 'Sheets awaiting review',
  outstandingIncompletes: 'Outstanding Incompletes',
  myAcademicRecord: 'My academic record',
  semesterWindows: 'Semester and open windows',
  catalogSummary: 'Catalog',
  administration: 'Administration',
} as const

type CardKey = keyof typeof CARDS
const ALL_CARDS = Object.keys(CARDS) as CardKey[]

// `multi@`'s union, named once: the Card-set test asserts it, and the axe scans below wait for
// exactly this many Cards to have resolved before scanning.
const MULTI_CARDS: CardKey[] = ['adviseeRequests', 'myGradeSheets', 'outstandingIncompletes']

// The canonical role accounts (shared dev password). Each carries a real footprint since ticket
// 02, so these dashboards render populated rather than as a wall of zeros.
const APPLICANT = 'r01@ejust.test'
const STUDENT = 'r02@ejust.test'
const ADVISOR = 'r03@ejust.test'
const INSTRUCTOR = 'r04@ejust.test'
const DEAN = 'r07@ejust.test'
const ADMIN = 'r09@ejust.test'
// Advisor **and** Instructor — the one account built to prove the composition rule.
const MULTI = 'multi@ejust.test'

const COMBOS = [
  { scheme: 'light', lang: 'en', dir: 'ltr' },
  { scheme: 'dark', lang: 'en', dir: 'ltr' },
  { scheme: 'light', lang: 'ar', dir: 'rtl' },
  { scheme: 'dark', lang: 'ar', dir: 'rtl' },
] as const

/**
 * Seeded sign-in (the shared dev password), landing on the Dashboard at `/`.
 *
 * The redirect gets a longer budget than the 5s default because a sign-in is three round trips
 * (CSRF cookie, login, `/me`) and this file's first test is often the first request a
 * just-booted `php artisan serve` answers — a cold start that has been measured brushing past
 * five seconds on Windows. The rest of the suite keeps the default; only the boundary that pays
 * the cold start is widened.
 */
async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/$/, { timeout: 20_000 })
}

/**
 * Sign in and **pin English** — what every Card-set test needs and the axe scans do not. The
 * locale is per-user server state (`preferred_locale` on `/me`), so a test asserting English
 * headings must state that rather than inherit whatever ran before it (the grading.spec.ts
 * precedent).
 */
async function loginInEnglish(page: Page, email: string) {
  await login(page, email)
  await page.getByRole('button', { name: 'English' }).click()
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')
}

/**
 * One Card, located by its heading — an `h2` under the page's single `h1` (DashboardCard).
 * `exact`, so a Card can never be found by a substring of another Card's title and the table
 * above means precisely what it says.
 */
function cardHeading(page: Page, key: CardKey) {
  return page.getByRole('heading', { level: 2, name: CARDS[key], exact: true })
}

/** A per-test screenshot, attached to the HTML report for the manual review gate — not asserted. */
async function capture(page: Page, testInfo: TestInfo, name: string) {
  await testInfo.attach(name, {
    body: await page.screenshot({ fullPage: true }),
    contentType: 'image/png',
  })
}

/**
 * The Card set assertion: every Card in `expected` renders, and **every other Card in the
 * inventory does not**. The presence pass runs first so the grid has rendered before anything is
 * asserted absent, and the closing count refuses a Card that is on the page under some heading
 * this file does not know about.
 */
async function expectCardSet(page: Page, expected: readonly CardKey[]) {
  for (const key of expected) {
    await expect(cardHeading(page, key), `${CARDS[key]} should be on this Dashboard`).toBeVisible()
  }

  for (const key of ALL_CARDS.filter((card) => !expected.includes(card))) {
    await expect(cardHeading(page, key), `${CARDS[key]} must not be on this Dashboard`).toHaveCount(
      0,
    )
  }

  // Every Card is one `h2`, and the identity header above the grid contributes none — so the
  // Dashboard holds exactly as many second-level headings as it holds Cards.
  await expect(page.getByRole('heading', { level: 2 })).toHaveCount(expected.length)

  // A Card renders its heading **above** its loading/empty/error branches, so the assertions
  // above would stay green with every Card endpoint returning 500. No Card may be in its error
  // state: this is where a Card's own `can:` gate refusing a request it should have allowed
  // shows up, which is the half of the server/client agreement the absence assertions cannot see.
  await expect(page.getByRole('alert'), 'no Card may be in its error state').toHaveCount(0)
  await expect(page.locator('[aria-busy="true"]'), 'every Card resolved').toHaveCount(0)
}

/**
 * ADR-0024's other half: a User holding several Roles sees the union **on one page**, and there is
 * no "view as role" control — so there is no hidden mode in which they might be looking at an
 * incomplete picture. Asserted as the absence of any such control in the chrome, plus the account
 * menu (the one place such a switch would plausibly live) holding exactly one item.
 */
async function expectNoRoleSwitcher(page: Page) {
  // The two load-bearing assertions, because they are the ones that fail whatever a switcher
  // were to be *called*: no select of any kind exists in the shell, so a role picker cannot hide
  // as one, and the account menu — the one place such a control would plausibly live — holds
  // exactly one item, which is Log out.
  await expect(page.getByRole('combobox')).toHaveCount(0)
  await page.getByRole('button', { name: 'Account menu' }).click()
  await expect(page.getByRole('menuitem')).toHaveCount(1)
  await expect(page.getByRole('menuitem', { name: 'Log out' })).toBeVisible()
  await page.keyboard.press('Escape')

  // A net for the wording a future switcher would most likely arrive under. It cannot fail today
  // — no such copy exists — which is the point: it is here to fail the day someone adds it.
  const switcher = /view as|switch role|switch to|acting as|change role|role switch/i
  await expect(page.getByRole('button', { name: switcher })).toHaveCount(0)
  await expect(page.getByRole('link', { name: switcher })).toHaveCount(0)
}

// WCAG AA gate (design-system.md §12): axe on the real screen, no serious/critical.
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

test.describe('the Dashboard composes Cards from the acting user’s access (ADR-0024)', () => {
  test('an Applicant sees their own Application Card and no queue', async ({ page }) => {
    await loginInEnglish(page, APPLICANT)

    await expectCardSet(page, ['myApplication'])
  })

  test('a Student sees the two persona Cards, with a real CGPA rather than a placeholder', async ({
    page,
  }) => {
    await loginInEnglish(page, STUDENT)

    await expectCardSet(page, ['myRegistration', 'myAcademicRecord'])

    // `r02@` is a Student with two completed Semesters of finalized Grades (ticket 02) precisely
    // so both figures are computable. A dash means "not yet calculated" — the right rendering of
    // an empty record and the wrong outcome for a seeded one. The Card shows exactly two figures,
    // CGPA and latest Semester GPA, so asserting **both** are real numbers is what pins the CGPA
    // rather than being satisfied by whichever one happened to render.
    const record = page.getByRole('region', { name: CARDS.myAcademicRecord })
    await expect(record.getByText(/^[0-4]\.\d{2}$/)).toHaveCount(2)
    await expect(record.getByText('—')).toHaveCount(0)
    await expect(record.getByText(/credit hours? earned so far/)).toBeVisible()

    // The University ID stays on the Dashboard (ADR-0013), above the grid.
    await expect(page.getByText('University ID')).toBeVisible()
  })

  test('an Advisor sees the advisee queue alone, with their own advisees in it', async ({
    page,
  }) => {
    await loginInEnglish(page, ADVISOR)

    await expectCardSet(page, ['adviseeRequests'])

    // Scoped to this Advisor's own advisees: `r03@`'s waiting advisee, never `multi@`'s.
    const requests = page.getByRole('region', { name: CARDS.adviseeRequests })
    await expect(requests.getByRole('link', { name: 'Mariam Shukri' })).toBeVisible()
    await expect(requests.getByText('Hana Reda')).toHaveCount(0)
  })

  test('an Instructor sees their two grading Cards and nothing from admissions', async ({
    page,
  }) => {
    await loginInEnglish(page, INSTRUCTOR)

    // The Incomplete queue belongs to the Instructor persona; sheet review belongs only to R06.
    await expectCardSet(page, ['myGradeSheets', 'outstandingIncompletes'])
  })

  test('a Dean sees the admissions and configuration Cards their Faculty scope gives them', async ({
    page,
  }) => {
    await loginInEnglish(page, DEAN)

    await expectCardSet(page, [
      'screeningQueue',
      'admissionDecisions',
      'semesterWindows',
      'catalogSummary',
    ])
  })

  test('multi@ sees the union of both its Roles on one page, with no role switcher', async ({
    page,
  }, testInfo) => {
    // The phase's headline assertion. `multi@` holds Advisor **and** Instructor, and ticket 02
    // gives it both advisees with a pending request and Offerings with an unfinalized Sheet, so
    // the union is genuinely visible rather than two empty states side by side.
    await loginInEnglish(page, MULTI)

    await expectCardSet(page, MULTI_CARDS)

    // Both Roles are acknowledged in the identity header, on the one page.
    await expect(page.getByText('R03 · Academic Advisor')).toBeVisible()
    await expect(page.getByText('R04 · Instructor')).toBeVisible()

    // The Advisor half: this account's own advisee, waiting on it.
    const requests = page.getByRole('region', { name: CARDS.adviseeRequests })
    await expect(requests.getByRole('link', { name: 'Hana Reda' })).toBeVisible()

    // The Instructor half, at the same time and without navigating: an Offering of its own,
    // linking into that Offering's Grade Sheet.
    const sheets = page.getByRole('region', { name: CARDS.myGradeSheets })
    await expect(sheets.getByRole('link', { name: /CSE532/ })).toBeVisible()

    await expectNoRoleSwitcher(page)

    // The phase's demo screen, attached for the manual review gate.
    await capture(page, testInfo, 'multi-role-dashboard.png')
  })

  test('an administrator sees the administration set only, never the union they inherit', async ({
    page,
  }) => {
    // R09 holds every Permission (ADR-0008); that is an authorization fact, not a description of
    // an administrator's job — so no queue Card and no persona Card, though they inherit the
    // Permission gating every one of them.
    await loginInEnglish(page, ADMIN)

    await expectCardSet(page, ['administration', 'semesterWindows', 'catalogSummary'])
  })
})

test.describe('the Dashboard holds WCAG AA', () => {
  // The most-viewed screen in the application and the one every user meets first, so it is scanned
  // across all four combos rather than one. `multi@` is the subject because its grid is the
  // richest: four Cards carrying counts, linked rows and status chips.
  for (const { scheme, lang, dir } of COMBOS) {
    test(`the Dashboard passes axe in ${scheme} / ${lang}`, async ({ page }, testInfo) => {
      // Pre-set the colour scheme (the pre-paint script reads it) before the first paint.
      await page.addInitScript((s) => localStorage.setItem('mui-mode', s), scheme)

      await login(page, MULTI)
      await page.getByRole('button', { name: lang === 'ar' ? 'العربية' : 'English' }).click()

      await expect(page.locator('html')).toHaveAttribute('dir', dir)
      await expect(page.locator('html')).toHaveAttribute('lang', lang)
      await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)

      // Scan the Cards **populated**, not as skeletons or error states: a grid of empty boxes
      // would pass axe while proving nothing about the screen a user actually meets.
      await expect(page.getByRole('region', { name: /.+/ })).toHaveCount(MULTI_CARDS.length)
      await expect(page.locator('[aria-busy="true"]')).toHaveCount(0)
      await expect(page.getByRole('alert')).toHaveCount(0)

      await expectNoSeriousA11y(page, `dashboard — ${scheme} / ${lang}`)

      // Per-combo screenshot artifact — for human review, NOT pixel-asserted (style.spec.ts).
      await capture(page, testInfo, `dashboard-${scheme}-${lang}.png`)

      // Leave the seeded account in English (headline.spec.ts's "leave the seeded user clean"):
      // `preferred_locale` is server state, and an Arabic account left behind is exactly the
      // cross-spec trap playwright.config.ts documents.
      await page.getByRole('button', { name: 'English' }).click()
      await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')
    })
  }
})
