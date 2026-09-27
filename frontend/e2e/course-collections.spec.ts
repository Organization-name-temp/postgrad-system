import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const locale of ['en', 'ar'] as const) {
  test(`Course filters and authenticated export retain their URL in ${locale}`, async ({
    page,
  }, testInfo) => {
    await page.goto('/login')
    await page.getByLabel('Email').fill('r07@ejust.test')
    await page.getByLabel('Password').fill('password')
    await page.getByRole('button', { name: 'Sign in' }).click()
    await expect(page).toHaveURL(/\/$/)
    await page.getByRole('button', { name: 'English' }).click()
    await page.goto('/catalog/courses?q=CSE5&level=500')
    await expect(page.getByText('CSE511', { exact: true })).toBeVisible()
    if (locale === 'ar') await page.getByRole('button', { name: 'العربية' }).click()
    await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr')
    const search = page.getByLabel(
      locale === 'ar' ? 'البحث بالرمز أو الاسم' : 'Search by code or name',
    )
    await expect(search).toHaveValue('CSE5')
    await expect(page).toHaveURL(/q=CSE5&level=500/)
    const scan = await new AxeBuilder({ page }).analyze()
    expect(
      scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
    ).toEqual([])
    const download = page.waitForEvent('download')
    await page
      .getByRole('button', {
        name: locale === 'ar' ? 'تصدير جميع الصفوف المطابقة' : 'Export all filtered rows',
      })
      .click()
    const workbook = await download
    expect(workbook.suggestedFilename()).toBe('courses.xlsx')
    expect(await workbook.failure()).toBeNull()
    await workbook.saveAs(testInfo.outputPath(`courses-${locale}.xlsx`))
    await testInfo.attach(`courses-${locale}`, {
      body: await page.screenshot({ fullPage: true }),
      contentType: 'image/png',
    })
    await page.reload()
    await expect(search).toHaveValue('CSE5')
    await page.getByRole('button', { name: locale === 'ar' ? 'مقرر جديد' : 'New course' }).click()
    const form = page.getByRole('dialog', { name: locale === 'ar' ? 'مقرر جديد' : 'New course' })
    const department = form.getByRole('combobox', {
      name: locale === 'ar' ? 'القسم' : 'Department',
    })
    await department.fill('CE')
    await page
      .getByRole('option', { name: locale === 'ar' ? /هندسة الحاسوب/ : /Computer Engineering/ })
      .click()
    await expect(department).toHaveValue(locale === 'ar' ? /هندسة الحاسوب/ : /Computer Engineering/)
    await department.click()
    const universityWide = locale === 'ar' ? 'على مستوى الجامعة' : 'University-wide'
    await page.getByRole('option', { name: universityWide }).click()
    await expect(department).toHaveValue(universityWide)
    const formScan = await new AxeBuilder({ page }).analyze()
    expect(
      formScan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
    ).toEqual([])
    await form.getByRole('button', { name: locale === 'ar' ? 'إلغاء' : 'Cancel' }).click()
    await page.getByRole('button', { name: 'English' }).click()
  })
}
