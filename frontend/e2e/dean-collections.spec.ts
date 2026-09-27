import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const locale of ['en', 'ar'] as const) {
  test(`Dean review bookmark and authenticated download work in ${locale}`, async ({
    page,
  }, testInfo) => {
    await page.goto('/login')
    await page.getByLabel('Email').fill('r07@ejust.test')
    await page.getByLabel('Password').fill('password')
    await page.getByRole('button', { name: 'Sign in' }).click()
    await expect(page).toHaveURL(/\/$/)
    await page.getByRole('button', { name: locale === 'ar' ? 'العربية' : 'English' }).click()
    await page.goto('/dean?q=NoSuchPhase7Candidate&degree_level=MSc&sort=-age_days')
    const search = page.getByLabel(locale === 'ar' ? 'البحث عن المتقدمين' : 'Search applicants')
    await expect(search).toHaveValue('NoSuchPhase7Candidate')
    await page.reload()
    await expect(search).toHaveValue('NoSuchPhase7Candidate')
    const pending = page.waitForEvent('download')
    await page
      .getByRole('button', {
        name: locale === 'ar' ? 'تصدير جميع الصفوف المطابقة' : 'Export all filtered rows',
      })
      .click()
    const download = await pending
    expect(download.suggestedFilename()).toBe('applications.xlsx')
    expect(await download.failure()).toBeNull()
    await download.saveAs(testInfo.outputPath(`dean-review-${locale}.xlsx`))
    const scan = await new AxeBuilder({ page }).analyze()
    expect(
      scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
    ).toEqual([])
    await page.getByRole('button', { name: 'English' }).click()
  })
}
