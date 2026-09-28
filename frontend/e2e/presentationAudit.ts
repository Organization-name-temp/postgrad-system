import { selectAuthenticatedLanguage } from './authenticatedLanguage'
import { settledPresentation } from './settledPresentation'
import AxeBuilder from '@axe-core/playwright'
import { expect, type Page } from '@playwright/test'

/** Reuse an owner's live workflow state; never replay its mutation for presentation. */
export async function auditPresentation(page: Page) {
  await expect(page.locator('main').getByRole('progressbar')).toHaveCount(0)
  await expect(page.locator('main .MuiSkeleton-root')).toHaveCount(0)
  const originalScheme = await page.locator('html').getAttribute('data-mui-color-scheme')
  const viewport = page.viewportSize()
  const values = () =>
    page
      .locator('main input:not([type="file"]):not([role="combobox"]), main textarea')
      .evaluateAll((elements) => elements.map((element) => (element as HTMLInputElement).value))
  const originalValues = await values()
  for (const scheme of ['light', 'dark'] as const) {
    if ((await page.locator('html').getAttribute('data-mui-color-scheme')) !== scheme) {
      await page
        .getByRole('button', { name: /Toggle light and dark theme|تبديل السمة الفاتحة والداكنة/ })
        .click()
    }
    for (const locale of ['en', 'ar'] as const) {
      await selectAuthenticatedLanguage(page, locale)
      await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr')
      await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)
      await expect(page.locator('main').getByRole('progressbar')).toHaveCount(0)
      await expect(page.locator('main .MuiSkeleton-root')).toHaveCount(0)
      await expect.poll(values).toEqual(originalValues)
      for (const width of [390, 1280]) {
        await page.setViewportSize({ width, height: 900 })
        expect(
          await page.evaluate(
            () => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2,
          ),
          `${page.url()}: ${scheme}/${locale}/${width}`,
        ).toBe(true)
      }
      await settledPresentation(page)
      const scan = await new AxeBuilder({ page }).analyze()
      expect(
        scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
      ).toEqual([])
    }
  }
  await selectAuthenticatedLanguage(page, 'en')
  if ((await page.locator('html').getAttribute('data-mui-color-scheme')) !== originalScheme) {
    await page.getByRole('button', { name: 'Toggle light and dark theme', exact: true }).click()
  }
  if (viewport) await page.setViewportSize(viewport)
  // Callers often run another scan immediately after this audit restores its theme.
  await settledPresentation(page)
}
