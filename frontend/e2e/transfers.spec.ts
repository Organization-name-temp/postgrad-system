import {
  loginInEnglish as loginWithSavedPreference,
  selectAuthenticatedLanguage,
} from './authenticatedLanguage'
import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'

const surfaces = [
  {
    email: 'r02@ejust.test',
    path: '/transfers',
    en: 'Transfer requests',
    ar: 'طلبات تحويل الساعات',
  },
  {
    email: 'r03@ejust.test',
    path: '/transfers/advisor',
    en: 'Transfer recommendations',
    ar: 'توصيات التحويل',
  },
  {
    email: 'r06@ejust.test',
    path: '/transfers/department-head',
    en: 'Transfer equivalency',
    ar: 'معادلة التحويل',
  },
  {
    email: 'r08@ejust.test',
    path: '/transfers/council',
    en: 'Transfer decisions',
    ar: 'قرارات التحويل',
  },
] as const

async function login(page: Page, email: string) {
  await loginWithSavedPreference(page, email)
}

test.describe('Transfer workflow role surfaces', () => {
  for (const surface of surfaces) {
    test(`${surface.email} gets an accessible bilingual scoped surface`, async ({ page }) => {
      await login(page, surface.email)
      await page.goto(surface.path)
      await expect(page.getByRole('heading', { level: 1, name: surface.en })).toBeVisible()
      await expect(page.locator('.MuiSkeleton-root')).toHaveCount(0)
      await expect(page.getByRole('alert')).toHaveCount(0)

      await selectAuthenticatedLanguage(page, 'ar')
      await expect(page.getByRole('heading', { level: 1, name: surface.ar })).toBeVisible()
      await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
      await expect(page.locator('html')).toHaveAttribute('lang', 'ar')

      const { violations } = await new AxeBuilder({ page }).analyze()
      expect(
        violations.filter(({ impact }) => impact === 'serious' || impact === 'critical'),
      ).toEqual([])
    })
  }
})
