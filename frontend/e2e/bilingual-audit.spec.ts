import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

// Read-only Phase 7 audit. Workflow mutations remain covered by owner journeys.
const surfaces = [
  {
    name: 'Catalog management',
    email: 'r09@ejust.test',
    paths: [
      '/catalog',
      '/catalog/faculties',
      '/catalog/departments',
      '/catalog/programs',
      '/catalog/courses',
    ],
  },
  {
    name: 'Admissions staff',
    email: 'r05@ejust.test',
    paths: ['/', '/screening', '/interviews/scheduling', '/interviews/slots'],
  },
  { name: 'Admissions Dean', email: 'r07@ejust.test', paths: ['/', '/dean', '/dean/decisions'] },
  {
    name: 'Student academic',
    email: 'r02@ejust.test',
    paths: [
      '/',
      '/registration',
      '/schedule',
      '/academic-standing',
      '/grading/my-grades',
      '/exemptions',
      '/transfers',
      '/waivers',
    ],
  },
  {
    name: 'Advisor academic',
    email: 'r03@ejust.test',
    paths: [
      '/',
      '/advising',
      '/registration/on-behalf',
      '/termination/advisor',
      '/exemptions/advisor',
      '/transfers/advisor',
      '/waivers/advisor',
    ],
  },
  {
    name: 'Head academic',
    email: 'r06@ejust.test',
    paths: [
      '/grading/review',
      '/registration/capacity-overrides',
      '/termination/department-head',
      '/exemptions/department-head',
      '/transfers/department-head',
      '/waivers/department-head',
    ],
  },
  {
    name: 'Council academic',
    email: 'r08@ejust.test',
    paths: ['/transfers/council', '/waivers/council', '/termination/council'],
  },
  {
    name: 'Records Student',
    email: 'student.smoke@seed.ejust.test',
    paths: ['/records', '/records/requests', '/records/history'],
  },
  {
    name: 'Records staff',
    email: 'r05@ejust.test',
    paths: ['/records/requests', '/records/history', '/records/fee'],
  },
] as const

for (const surface of surfaces) {
  for (const scheme of ['light', 'dark'] as const) {
    for (const locale of ['en', 'ar'] as const) {
      test(`audit ${surface.name} ${scheme} ${locale}`, async ({ page }, testInfo) => {
        test.setTimeout(180_000)
        await page.addInitScript((mode) => localStorage.setItem('mui-mode', mode), scheme)
        await page.goto('/login')
        await page.getByLabel('Email').fill(surface.email)
        await page.getByLabel('Password').fill('password')
        await page.getByRole('button', { name: 'Sign in' }).click()
        await expect(page).toHaveURL(/\/$/)
        await page.getByRole('button', { name: locale === 'ar' ? 'العربية' : 'English' }).click()
        const coverage: unknown[] = []
        for (const path of surface.paths) {
          await page.goto(path)
          await expect(page).toHaveURL(new RegExp(`${path.replaceAll('/', '\\/')}$`))
          await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()
          await expect(page.locator('main').getByRole('progressbar')).toHaveCount(0)
          await expect(page.locator('main .MuiSkeleton-root')).toHaveCount(0)
          await expect(page.locator('main [aria-busy="true"]')).toHaveCount(0)
          await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr')
          await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)
          const text = await page.locator('main').innerText()
          expect(text, `${path}: raw translation keys`).not.toMatch(
            /\b(?:common|auth|catalog|admissions|enrollment|grading|records|academicStanding)\.[a-zA-Z]+\.[a-zA-Z]+/,
          )
          const scan = await new AxeBuilder({ page }).analyze()
          expect(
            scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
            `${path}: accessibility`,
          ).toEqual([])
          for (const width of [390, 1280]) {
            await page.setViewportSize({ width, height: 900 })
            expect(
              await page.evaluate(
                () =>
                  document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2,
              ),
              `${path}: viewport ${width} fits with local table scrolling`,
            ).toBe(true)
          }
          coverage.push({
            path,
            scheme,
            locale,
            heading: await page.getByRole('heading', { level: 1 }).first().innerText(),
            viewports: [390, 1280],
            seriousOrCritical: 0,
          })
        }
        await testInfo.attach('surface-audit', {
          body: JSON.stringify(coverage, null, 2),
          contentType: 'application/json',
        })
        await page.getByRole('button', { name: 'English' }).click()
      })
    }
  }
}
