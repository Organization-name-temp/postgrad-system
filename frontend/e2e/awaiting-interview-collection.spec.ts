import {
  loginInEnglish as loginWithSavedPreference,
  selectAuthenticatedLanguage,
} from './authenticatedLanguage'
import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const locale of ['en', 'ar'] as const) {
  test(`Awaiting interview criteria and authenticated workbook work in ${locale}`, async ({
    page,
  }, testInfo) => {
    await loginWithSavedPreference(page, 'r05@ejust.test')
    await selectAuthenticatedLanguage(page, locale)
    await page.goto(
      '/interviews/scheduling?awaiting_q=NoSuchSchedulingApplicant&awaiting_sort=-age_days&booked_q=Other',
    )
    const region = page.getByRole('region', {
      name: locale === 'ar' ? 'بانتظار الجدولة' : 'Awaiting scheduling',
    })
    const search = region.getByLabel(locale === 'ar' ? 'البحث عن المتقدمين' : 'Search applicants')
    await expect(search).toHaveValue('NoSuchSchedulingApplicant')
    await page.reload()
    await expect(search).toHaveValue('NoSuchSchedulingApplicant')
    const pending = page.waitForEvent('download')
    await region
      .getByRole('button', {
        name: locale === 'ar' ? 'تصدير جميع الصفوف المطابقة' : 'Export all filtered rows',
      })
      .click()
    const workbook = await pending
    expect(workbook.suggestedFilename()).toBe('applications.xlsx')
    expect(await workbook.failure()).toBeNull()
    await workbook.saveAs(testInfo.outputPath(`awaiting-interviews-${locale}.xlsx`))
    const scan = await new AxeBuilder({ page }).analyze()
    expect(
      scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
    ).toEqual([])
    await selectAuthenticatedLanguage(page, 'en')
  })
}
