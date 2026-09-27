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
      if (resource === 'departments') {
        await page
          .getByRole('button', { name: locale === 'ar' ? 'قسم جديد' : 'New department' })
          .click()
        const form = page.getByRole('dialog', {
          name: locale === 'ar' ? 'قسم جديد' : 'New department',
        })
        const faculty = form.getByRole('combobox', { name: locale === 'ar' ? 'الكلية' : 'Faculty' })
        await faculty.fill('FoE')
        await page
          .getByRole('option', { name: locale === 'ar' ? /الهندسة/ : /Engineering/ })
          .click()
        await expect(faculty).toHaveValue(locale === 'ar' ? /الهندسة/ : /Engineering/)
        const formScan = await new AxeBuilder({ page }).analyze()
        expect(
          formScan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
        ).toEqual([])
        await form.getByRole('button', { name: locale === 'ar' ? 'إلغاء' : 'Cancel' }).click()
      }
      if (resource === 'programs') {
        await page
          .getByRole('button', { name: locale === 'ar' ? 'برنامج جديد' : 'New program' })
          .click()
        const form = page.getByRole('dialog', {
          name: locale === 'ar' ? 'برنامج جديد' : 'New program',
        })
        const department = form.getByRole('combobox', {
          name: locale === 'ar' ? 'القسم' : 'Department',
        })
        await department.fill('CE')
        await page
          .getByRole('option', { name: locale === 'ar' ? /هندسة الحاسوب/ : /Computer Engineering/ })
          .click()
        await expect(department).toHaveValue(
          locale === 'ar' ? /هندسة الحاسوب/ : /Computer Engineering/,
        )
        const formScan = await new AxeBuilder({ page }).analyze()
        expect(
          formScan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
        ).toEqual([])
        await form.getByRole('button', { name: locale === 'ar' ? 'إلغاء' : 'Cancel' }).click()
      }
    }
    await page.getByRole('button', { name: 'English' }).click()
  })
}
