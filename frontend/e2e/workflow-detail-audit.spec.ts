import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

async function audit(page: Page) {
  await expect(page.locator('main').getByRole('progressbar')).toHaveCount(0)
  await expect(page.locator('main .MuiSkeleton-root')).toHaveCount(0)
  for (const width of [390, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2,
      ),
      `${page.url()}: width ${width}`,
    ).toBe(true)
    const result = await new AxeBuilder({ page }).analyze()
    expect(
      result.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
    ).toEqual([])
  }
}

const roles = [
  { name: 'Applicant form', email: 'r01@ejust.test', path: '/apply' },
  { name: 'Screening detail', email: 'r05@ejust.test', path: '/screening', link: '/screening/' },
  { name: 'Dean detail', email: 'r07@ejust.test', path: '/dean', link: '/dean/' },
  { name: 'Advisor detail', email: 'r03@ejust.test', path: '/advising', link: '/advising/' },
  {
    name: 'Attendance register',
    email: 'r04@ejust.test',
    path: '/offerings/mine',
    link: '/offerings/',
  },
  { name: 'Interviewer', email: 'interviewer.hoda@ejust.test', path: '/interviews/mine' },
] as const

for (const role of roles) {
  for (const scheme of ['light', 'dark'] as const) {
    for (const locale of ['en', 'ar'] as const) {
      test(`existing ${role.name} in ${scheme} ${locale}`, async ({ page }) => {
        test.setTimeout(120_000)
        await page.addInitScript((mode) => localStorage.setItem('mui-mode', mode), scheme)
        await page.goto('/login')
        await page.getByLabel('Email').fill(role.email)
        await page.getByLabel('Password').fill('password')
        await page.getByRole('button', { name: 'Sign in' }).click()
        await expect(page).toHaveURL(/\/$/)
        await page
          .getByRole('button', { name: locale === 'ar' ? 'العربية' : 'English', exact: true })
          .click()
        await page.goto(role.path)
        await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()
        await audit(page)
        if ('link' in role) {
          const link = page.locator(`main a[href^="${role.link}"]`).first()
          await expect(link).toBeVisible()
          await link.click()
          await expect(page).toHaveURL(
            new RegExp(
              `${role.link}\\d+${role.name === 'Attendance register' ? '/register' : ''}$`,
            ),
          )
          await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()
          await audit(page)
        }
        if (role.name === 'Applicant form') {
          const input = page.locator('input[name="name_en"]')
          await input.fill('Phase 7 unsaved applicant')
          await page
            .getByRole('button', { name: locale === 'ar' ? 'English' : 'العربية', exact: true })
            .click()
          await expect(input).toHaveValue('Phase 7 unsaved applicant')
          await audit(page)
        }
        await page.getByRole('button', { name: 'English', exact: true }).click()
      })
    }
  }
}
