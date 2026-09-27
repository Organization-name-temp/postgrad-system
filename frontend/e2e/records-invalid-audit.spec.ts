import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const scheme of ['light', 'dark'] as const) {
  for (const locale of ['en', 'ar'] as const) {
    test(`invalid public verification is private and readable in ${scheme} ${locale}`, async ({
      page,
    }) => {
      await page.addInitScript((mode) => localStorage.setItem('mui-mode', mode), scheme)
      const response = page.waitForResponse(
        (r) => r.url().includes('/api/v1/records/verify/') && r.request().method() === 'GET',
      )
      await page.goto('/records/verify/phase7-audit-invalid-token')
      expect((await response).status()).toBe(404)
      if (locale === 'ar') await page.getByRole('button', { name: 'العربية', exact: true }).click()
      await expect(page.getByRole('alert')).toHaveText(
        locale === 'ar'
          ? 'رابط التحقق غير صالح أو غير متاح.'
          : 'This verification link is invalid or unavailable.',
      )
      await expect(page.locator('article')).toHaveCount(0)
      await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr')
      await expect(page.getByRole('navigation')).toHaveCount(0)
      await expect(page.getByText('31000000000004')).toHaveCount(0)
      for (const width of [390, 1280]) {
        await page.setViewportSize({ width, height: 900 })
        expect(
          await page.evaluate(
            () => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2,
          ),
        ).toBe(true)
        const scan = await new AxeBuilder({ page }).analyze()
        expect(
          scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
        ).toEqual([])
      }
    })
  }
}
