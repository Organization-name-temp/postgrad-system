import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const account of ['r05', 'r07']) {
  for (const locale of ['en', 'ar'] as const) {
    test(`Termination scoped Cases export and paper for ${account} in ${locale}`, async ({
      page,
    }, testInfo) => {
      await page.addInitScript(
        (mode) => localStorage.setItem('mui-mode', mode),
        account === 'r07' ? 'dark' : 'light',
      )
      await page.goto('/login')
      await page.getByLabel('Email').fill(`${account}@ejust.test`)
      await page.getByLabel('Password').fill('password')
      await page.getByRole('button', { name: 'Sign in' }).click()
      await expect(page).toHaveURL(/\/$/)
      await page.getByRole('button', { name: locale === 'ar' ? 'العربية' : 'English' }).click()
      const title = locale === 'ar' ? 'تقرير إنهاء القيد' : 'Termination Report'
      await expect(page.getByRole('link', { name: title, exact: true })).toBeVisible()
      const criteria = 'q=Phase+7+report&history=include'
      const response = page.waitForResponse(
        (r) =>
          r.url().includes('/api/v1/academic-standing/reports/termination?') &&
          r.request().method() === 'GET',
      )
      await page.goto(`/analytics/termination?${criteria}`)
      const summary = (await (await response).json()).data
      expect(summary.case_count).toBe(3)
      expect(summary.student_count).toBe(3)
      await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible()
      const chart = locale === 'ar' ? 'مراحل الحالات ونتائجها' : 'Case stages and outcomes'
      const table = page.getByRole('table', { name: chart, exact: true })
      await page.emulateMedia({ media: 'print' })
      await expect(table).toBeVisible()
      const paper = await page.getByRole('article', { name: title }).evaluate((article) => ({
        dir: getComputedStyle(article).direction,
        colors: [...article.querySelectorAll('h1,td,th,dt,dd')].map(
          (el) => getComputedStyle(el).color,
        ),
        fits: [...article.querySelectorAll('table')].every(
          (el) => el.getBoundingClientRect().width <= article.getBoundingClientRect().width + 1,
        ),
      }))
      expect(paper.dir).toBe(locale === 'ar' ? 'rtl' : 'ltr')
      expect(paper.colors.every((color) => color === 'rgb(0, 0, 0)')).toBe(true)
      expect(paper.fits).toBe(true)
      await page.pdf({
        path: testInfo.outputPath(`termination-${account}-${locale}.pdf`),
        printBackground: true,
        preferCSSPageSize: true,
      })
      await page.emulateMedia({ media: 'screen' })
      await page
        .getByRole('region', { name: chart, exact: true })
        .getByRole('button', { name: locale === 'ar' ? 'عرض كجدول' : 'View as table' })
        .click()
      await expect(table.getByRole('row')).toHaveCount(8)
      const label =
        locale === 'ar' ? 'رصد حالة بانتظار تقييم المرشد' : 'Flagged: awaiting Advisor assessment'
      const link = table.getByRole('link', { name: label, exact: true })
      await expect(link).toHaveAttribute(
        'href',
        `/analytics/termination/cases?${criteria}&state=AwaitingAdvisor`,
      )
      for (const row of await table.getByRole('row').all()) {
        if ((await row.getByRole('cell').allTextContents()).includes('0'))
          await expect(row.getByRole('link')).toHaveCount(0)
      }
      const reportScan = await new AxeBuilder({ page }).analyze()
      expect(
        reportScan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
      ).toEqual([])
      const listResponse = page.waitForResponse(
        (r) =>
          r.url().includes('/api/v1/academic-standing/reports/termination/cases?') &&
          r.request().method() === 'GET',
      )
      await link.focus()
      await link.press('Enter')
      const cases = await (await listResponse).json()
      expect(cases.meta.total).toBe(3)
      for (const field of ['evidence', 'fingerprint', 'national_id', 'advisor_note'])
        expect(JSON.stringify(cases)).not.toContain(field)
      const caseTable = page.getByRole('table', {
        name: locale === 'ar' ? 'حالات إنهاء القيد المطابقة' : 'Matching Termination Cases',
      })
      await expect(caseTable.getByRole('row')).toHaveCount(4)
      await expect(
        caseTable.getByRole('link', {
          name: locale === 'ar' ? 'فتح مسار المراجعة المصرح به' : 'Open authorized review workflow',
        }),
      ).toHaveCount(0)
      const pendingDownload = page.waitForEvent('download')
      await page
        .getByRole('button', {
          name: locale === 'ar' ? 'تصدير جميع الصفوف المطابقة' : 'Export all filtered rows',
        })
        .click()
      const download = await pendingDownload
      expect(download.suggestedFilename()).toBe('termination-report-cases.xlsx')
      expect(await download.failure()).toBeNull()
      await download.saveAs(testInfo.outputPath(`termination-${account}-${locale}.xlsx`))
      const listScan = await new AxeBuilder({ page }).analyze()
      expect(
        listScan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
      ).toEqual([])
      await page.goBack()
      await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible()
      await page.getByRole('button', { name: 'English' }).click()
    })
  }
}
