import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const locale of ['en', 'ar'] as const) {
  test(`Cycle criteria and authenticated workbook work in ${locale}`, async ({
    page,
  }, testInfo) => {
    await page.goto('/login')
    await page.getByLabel('Email').fill('r05@ejust.test')
    await page.getByLabel('Password').fill('password')
    await page.getByRole('button', { name: 'Sign in' }).click()
    await expect(page).toHaveURL(/\/$/)
    await page.getByRole('button', { name: locale === 'ar' ? 'العربية' : 'English' }).click()
    await page.goto('/admin/cycles?q=Fall&year_min=2026&is_open=true&sort=-year')
    const search = page.getByLabel(
      locale === 'ar' ? 'البحث في دورات القبول' : 'Search Admission Cycles',
    )
    await expect(search).toHaveValue('Fall')
    await page.reload()
    await expect(search).toHaveValue('Fall')
    const pending = page.waitForEvent('download')
    await page
      .getByRole('button', {
        name: locale === 'ar' ? 'تصدير جميع الصفوف المطابقة' : 'Export all filtered rows',
      })
      .click()
    const workbook = await pending
    expect(workbook.suggestedFilename()).toBe('admission-cycles.xlsx')
    expect(await workbook.failure()).toBeNull()
    await workbook.saveAs(testInfo.outputPath(`cycles-${locale}.xlsx`))
    const scan = await new AxeBuilder({ page }).analyze()
    expect(
      scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
    ).toEqual([])
    await page.getByRole('button', { name: 'English' }).click()
  })
}
