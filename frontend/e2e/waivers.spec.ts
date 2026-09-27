import {
  loginInEnglish as loginWithSavedPreference,
  selectAuthenticatedLanguage,
} from './authenticatedLanguage'
import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'

const SURFACES = [
  {
    role: 'Student',
    email: 'r02@ejust.test',
    path: '/waivers',
    heading: { en: 'Course Waivers', ar: 'الإعفاء من المقررات' },
  },
  {
    role: 'Advisor',
    email: 'r03@ejust.test',
    path: '/waivers/advisor',
    heading: { en: 'Waiver recommendations', ar: 'توصيات الإعفاء من مقرر' },
  },
  {
    role: 'Department Head',
    email: 'r06@ejust.test',
    path: '/waivers/department-head',
    heading: { en: 'Waiver equivalency verification', ar: 'التحقق من تكافؤ الإعفاء' },
  },
  {
    role: 'Council',
    email: 'r08@ejust.test',
    path: '/waivers/council',
    heading: { en: 'Council Waiver decisions', ar: 'قرارات المجلس بشأن الإعفاء' },
  },
] as const

async function login(page: Page, email: string) {
  await loginWithSavedPreference(page, email)
  await selectAuthenticatedLanguage(page, 'en')
}

test.describe('Waiver persona surfaces are bilingual, RTL-safe, and accessible', () => {
  for (const surface of SURFACES) {
    for (const language of ['en', 'ar'] as const) {
      test(`${surface.role} Waiver surface in ${language}`, async ({ page }) => {
        await login(page, surface.email)
        await page.goto(surface.path)
        await selectAuthenticatedLanguage(page, language)

        await expect(
          page.getByRole('heading', { level: 1, name: surface.heading[language] }),
        ).toBeVisible()
        await expect(page.locator('html')).toHaveAttribute('lang', language)
        await expect(page.locator('html')).toHaveAttribute('dir', language === 'ar' ? 'rtl' : 'ltr')
        await expect(page.locator('.MuiSkeleton-root')).toHaveCount(0)
        await expect(page.getByRole('alert')).toHaveCount(0)

        const { violations } = await new AxeBuilder({ page }).analyze()
        expect(
          violations.filter(({ impact }) => impact === 'serious' || impact === 'critical'),
        ).toEqual([])
      })
    }
  }
})
