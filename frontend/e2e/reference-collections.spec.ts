import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const locale of ['en', 'ar'] as const) {
  test(`Catalog reference criteria and downloads work in ${locale}`, async ({ page }, testInfo) => {
    await page.goto('/login')
    await page.getByLabel('Email').fill('r07@ejust.test')
    await page.getByLabel('Password').fill('password')
    await page.getByRole('button', { name: 'Sign in' }).click()
    await expect(page).toHaveURL(/\/$/)
    await page.getByRole('button', { name: locale === 'ar' ? 'العربية' : 'English' }).click()
    for (const [resource, code] of [
      ['faculties', 'FoE'],
      ['departments', 'CE'],
      ['programs', 'MSC-CE'],
    ] as const) {
      await page.goto(`/catalog/${resource}?q=${code}&is_active=true`)
      await expect(page.getByText(code, { exact: true })).toBeVisible()
      await expect(
        page.getByLabel(locale === 'ar' ? 'البحث بالرمز أو الاسم' : 'Search by code or name'),
      ).toHaveValue(code)
      const scan = await new AxeBuilder({ page }).analyze()
      expect(
        scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
      ).toEqual([])
      const pending = page.waitForEvent('download')
      await page
        .getByRole('button', {
          name: locale === 'ar' ? 'تصدير جميع الصفوف المطابقة' : 'Export all filtered rows',
        })
        .click()
      const download = await pending
      expect(download.suggestedFilename()).toBe(`${resource}.xlsx`)
      expect(await download.failure()).toBeNull()
      await download.saveAs(testInfo.outputPath(`${resource}-${locale}.xlsx`))
    }
    await page.getByRole('button', { name: 'English' }).click()
  })
}
