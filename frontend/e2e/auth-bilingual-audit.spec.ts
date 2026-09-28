import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const scheme of ['light', 'dark'] as const) {
  for (const locale of ['en', 'ar'] as const) {
    test(`public authentication preserves input in ${scheme} ${locale}`, async ({ page }) => {
      test.setTimeout(90_000)
      await page.addInitScript((mode) => localStorage.setItem('mui-mode', mode), scheme)
      for (const path of ['/login', '/register']) {
        await page.goto(path)
        const email = page.locator('input[type="email"]')
        await expect(email).toBeVisible()
        await email.fill('phase7.audit@example.test')
        await page.locator('input[type="password"]').first().fill('preserved input')
        await page
          .getByRole('button', { name: locale === 'ar' ? 'العربية' : 'English', exact: true })
          .click()
        await expect(email).toHaveValue('phase7.audit@example.test')
        await expect(page.locator('input[type="password"]').first()).toHaveValue('preserved input')
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr')
        await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)
        const scan = await new AxeBuilder({ page }).analyze()
        expect(
          scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
        ).toEqual([])
        for (const width of [390, 1280]) {
          await page.setViewportSize({ width, height: 900 })
          expect(
            await page.evaluate(
              () =>
                document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2,
            ),
          ).toBe(true)
        }
      }
    })
  }
}
