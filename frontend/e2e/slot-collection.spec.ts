import {
  loginInEnglish as loginWithSavedPreference,
  selectAuthenticatedLanguage,
} from './authenticatedLanguage'
import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const locale of ['en', 'ar'] as const) {
  test(`Slot pool bookmarks and authenticated export work in ${locale}`, async ({
    page,
  }, testInfo) => {
    await loginWithSavedPreference(page, 'r05@ejust.test')
    await selectAuthenticatedLanguage(page, locale)
    await page.goto(
      '/interviews/slots?q=NoSuchRoom&is_open=true,false&mode=virtual,in_person&duration_minutes_min=30&sort=-scheduled_at,id',
    )
    const search = page.getByLabel(
      locale === 'ar' ? 'البحث عن أماكن المواعيد' : 'Search slot locations',
    )
    await expect(search).toHaveValue('NoSuchRoom')
    await page.reload()
    await expect(search).toHaveValue('NoSuchRoom')
    await expect(
      page.getByLabel(locale === 'ar' ? 'أدنى مدة بالدقائق' : 'Minimum duration (minutes)'),
    ).toHaveValue('30')
    await expect(page.getByLabel(locale === 'ar' ? 'الترتيب حسب' : 'Sort by')).toHaveValue(
      '-scheduled_at,id',
    )
    const pending = page.waitForEvent('download')
    await page
      .getByRole('button', {
        name: locale === 'ar' ? 'تصدير جميع الصفوف المطابقة' : 'Export all filtered rows',
      })
      .click()
    const workbook = await pending
    expect(workbook.suggestedFilename()).toBe('interview-slots.xlsx')
    expect(await workbook.failure()).toBeNull()
    await workbook.saveAs(testInfo.outputPath(`slots-${locale}.xlsx`))
    const scan = await new AxeBuilder({ page }).analyze()
    expect(
      scan.violations.filter((value) => value.impact === 'serious' || value.impact === 'critical'),
    ).toEqual([])
    await selectAuthenticatedLanguage(page, 'en')
  })
}
