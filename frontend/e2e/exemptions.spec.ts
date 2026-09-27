import {
  loginInEnglish as loginWithSavedPreference,
  selectAuthenticatedLanguage,
} from './authenticatedLanguage'
import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'

const COMBOS = [
  { scheme: 'light', lang: 'en', dir: 'ltr' },
  { scheme: 'dark', lang: 'en', dir: 'ltr' },
  { scheme: 'light', lang: 'ar', dir: 'rtl' },
  { scheme: 'dark', lang: 'ar', dir: 'rtl' },
] as const

const SURFACES = [
  {
    role: 'Student',
    email: 'r02@ejust.test',
    path: '/exemptions',
    heading: { en: 'Manual exemption requests', ar: 'طلبات الإعفاء اليدوي' },
  },
  {
    role: 'Advisor',
    email: 'r03@ejust.test',
    path: '/exemptions/advisor',
    heading: { en: 'Exemption recommendations', ar: 'توصيات الإعفاء' },
    otherHeading: { en: 'Exemption decisions', ar: 'قرارات الإعفاء' },
  },
  {
    role: 'Department Head',
    email: 'r06@ejust.test',
    path: '/exemptions/department-head',
    heading: { en: 'Exemption decisions', ar: 'قرارات الإعفاء' },
    otherHeading: { en: 'Exemption recommendations', ar: 'توصيات الإعفاء' },
  },
] as const

async function loginInEnglish(page: Page, email: string) {
  await loginWithSavedPreference(page, email)
  await selectAuthenticatedLanguage(page, 'en')
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')
}

async function expectNoSeriousA11y(page: Page, context: string) {
  const { violations } = await new AxeBuilder({ page }).analyze()
  const blocking = violations.filter(({ impact }) => impact === 'serious' || impact === 'critical')
  expect(
    blocking,
    `${context}: ${JSON.stringify(
      blocking.map(({ id, help, nodes }) => ({ id, help, nodes: nodes.length })),
      null,
      2,
    )}`,
  ).toEqual([])
}

test.describe('manual Exemption surfaces hold the design-system matrix', () => {
  for (const surface of SURFACES) {
    for (const { scheme, lang, dir } of COMBOS) {
      test(`${surface.role} passes axe in ${scheme} / ${lang}`, async ({ page }, testInfo) => {
        await page.addInitScript((mode) => localStorage.setItem('mui-mode', mode), scheme)
        await loginInEnglish(page, surface.email)
        await page.goto(surface.path)
        await selectAuthenticatedLanguage(page, lang)

        await expect(
          page.getByRole('heading', { level: 1, name: surface.heading[lang] }),
        ).toBeVisible()
        await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)
        await expect(page.locator('html')).toHaveAttribute('dir', dir)
        await expect(page.locator('html')).toHaveAttribute('lang', lang)
        await expect(page.locator('.MuiSkeleton-root')).toHaveCount(0)
        await expect(page.getByRole('alert')).toHaveCount(0)
        if ('otherHeading' in surface) {
          await expect(
            page.getByRole('heading', { level: 1, name: surface.otherHeading[lang] }),
          ).toHaveCount(0)
        }

        await expectNoSeriousA11y(page, `${surface.role} — ${scheme} / ${lang}`)
        await testInfo.attach(`exemptions-${surface.role}-${scheme}-${lang}`, {
          body: await page.screenshot({ fullPage: true }),
          contentType: 'image/png',
        })

        await selectAuthenticatedLanguage(page, 'en')
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')
      })
    }
  }
})
