import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const locale of ['en', 'ar'] as const) {
  for (const scope of ['booked', 'assigned'] as const) {
    test(`${scope} Interview collection preserves criteria and downloads in ${locale}`, async ({
      page,
    }, testInfo) => {
      await page.goto('/login')
      await page
        .getByLabel('Email')
        .fill(scope === 'booked' ? 'r05@ejust.test' : 'interviewer.hoda@ejust.test')
      await page.getByLabel('Password').fill('password')
      await page.getByRole('button', { name: 'Sign in' }).click()
      await expect(page).toHaveURL(/\/$/)
      await page.getByRole('button', { name: locale === 'ar' ? 'العربية' : 'English' }).click()
      const criteria = 'q=NoSuchInterviewApplicant&status=confirmed&mode=virtual&sort=-scheduled_at'
      await page.goto(
        scope === 'booked'
          ? `/interviews/scheduling?${criteria
              .split('&')
              .map((value) => `booked_${value}`)
              .join('&')}&awaiting_q=SeparateApplicant`
          : `/interviews/mine?${criteria}`,
      )
      const region =
        scope === 'booked'
          ? page.getByRole('region', {
              name: locale === 'ar' ? 'المقابلات المجدوَلة' : 'Scheduled interviews',
            })
          : page.locator('main')
      const search = region.getByLabel(locale === 'ar' ? 'البحث عن المتقدمين' : 'Search applicants')
      await expect(search).toHaveValue('NoSuchInterviewApplicant')
      await page.reload()
      await expect(search).toHaveValue('NoSuchInterviewApplicant')
      await expect(region.getByLabel(locale === 'ar' ? 'الترتيب حسب' : 'Sort by')).toHaveValue(
        '-scheduled_at',
      )
      const pending = page.waitForEvent('download')
      await region
        .getByRole('button', {
          name: locale === 'ar' ? 'تصدير جميع الصفوف المطابقة' : 'Export all filtered rows',
        })
        .click()
      const workbook = await pending
      expect(workbook.suggestedFilename()).toBe(
        scope === 'booked' ? 'interviews.xlsx' : 'assigned-interviews.xlsx',
      )
      expect(await workbook.failure()).toBeNull()
      await workbook.saveAs(testInfo.outputPath(`${scope}-interviews-${locale}.xlsx`))
      const scan = await new AxeBuilder({ page }).analyze()
      expect(
        scan.violations.filter(
          (value) => value.impact === 'serious' || value.impact === 'critical',
        ),
      ).toEqual([])
      await page.getByRole('button', { name: 'English' }).click()
    })
  }
}
