import {
  loginInEnglish as loginWithSavedPreference,
  selectAuthenticatedLanguage,
} from './authenticatedLanguage'
import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const locale of ['en', 'ar'] as const) {
  test(`Dean collections bookmark and authenticated download work in ${locale}`, async ({
    page,
  }, testInfo) => {
    await loginWithSavedPreference(page, 'r07@ejust.test')
    await selectAuthenticatedLanguage(page, locale)
    for (const [path, resource] of [
      ['/dean', 'dean-review'],
      ['/dean/decisions', 'dean-decisions'],
    ] as const) {
      await page.goto(
        `${path}?q=NoSuchPhase7Candidate&faculty_id=1,2&degree_level=MSc&sort=-age_days${path.endsWith('decisions') ? '&status=waitlisted' : ''}`,
      )
      const search = page.getByLabel(locale === 'ar' ? 'البحث عن المتقدمين' : 'Search applicants')
      await expect(search).toHaveValue('NoSuchPhase7Candidate')
      const faculty = page.getByLabel(locale === 'ar' ? 'معرّفات الكليات' : 'Faculty IDs')
      await expect(faculty).toHaveValue('1,2')
      await page.reload()
      await expect(search).toHaveValue('NoSuchPhase7Candidate')
      await expect(faculty).toHaveValue('1,2')
      const pending = page.waitForEvent('download')
      await page
        .getByRole('button', {
          name: locale === 'ar' ? 'تصدير جميع الصفوف المطابقة' : 'Export all filtered rows',
        })
        .click()
      const download = await pending
      expect(download.suggestedFilename()).toBe('applications.xlsx')
      expect(await download.failure()).toBeNull()
      await download.saveAs(testInfo.outputPath(`${resource}-${locale}.xlsx`))
      const scan = await new AxeBuilder({ page }).analyze()
      expect(
        scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
      ).toEqual([])
    }
    await selectAuthenticatedLanguage(page, 'en')
  })
}
