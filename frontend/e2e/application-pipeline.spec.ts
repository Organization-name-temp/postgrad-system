import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const account of ['r05', 'r07']) {
  for (const locale of ['en', 'ar'] as const) {
    test(`Pipeline exact stage drill and export work for ${account} in ${locale}`, async ({
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
      await expect(
        page.getByRole('link', {
          name: locale === 'ar' ? 'مسار طلبات القبول' : 'Application Pipeline',
        }),
      ).toBeVisible()
      const requestHeaders = { Accept: 'application/json', Referer: page.url() }
      const cyclesResponse = await page.request.get('/api/v1/admission-cycles?per_page=100', {
        headers: requestHeaders,
      })
      expect(cyclesResponse.ok()).toBeTruthy()
      const cycles = (await cyclesResponse.json()).data as {
        id: number
        name_en: string
        name_ar: string
      }[]
      let selected: (typeof cycles)[number] | undefined
      for (const cycle of cycles) {
        const response = await page.request.get(
          `/api/v1/admissions/reports/application-pipeline?admission_cycle_id=${cycle.id}&degree_level=MSc`,
          { headers: requestHeaders },
        )
        expect(response.ok()).toBeTruthy()
        if ((await response.json()).data.statuses.under_sa_review > 0) {
          selected = cycle
          break
        }
      }
      expect(selected, 'The seeded persona needs a visible review cohort').toBeDefined()
      if (!selected) throw new Error('No seeded Pipeline cohort')
      const url = `/analytics/application-pipeline?admission_cycle_id=${selected.id}&degree_level=MSc`
      const pendingReport = page.waitForResponse(
        (response) =>
          response.url().includes('/admissions/reports/application-pipeline') &&
          response.request().method() === 'GET',
      )
      await page.goto(url)
      const summary = (await (await pendingReport).json()).data
      const expectedCount = summary.statuses.under_sa_review as number
      const title = locale === 'ar' ? 'مسار طلبات القبول' : 'Application Pipeline'
      await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible()
      await expect(
        page
          .getByRole('img', { name: title })
          .getByText(locale === 'ar' ? 'مقبول بشروط' : 'Conditionally accepted', { exact: true }),
      ).toBeVisible()
      await expect(
        page
          .getByRole('img', { name: title })
          .locator('.MuiChartsAxis-directionX')
          .getByText('0', { exact: true }),
      ).toBeVisible()
      await expect(
        page.getByRole('combobox', {
          name: locale === 'ar' ? 'دورة القبول' : 'Admission Cycle',
          exact: true,
        }),
      ).toHaveValue(locale === 'ar' ? selected.name_ar : selected.name_en)
      await page.setViewportSize({ width: 794, height: 1123 })
      await page.emulateMedia({ media: 'print' })
      const printedCriteria = page.getByRole('region', {
        name: locale === 'ar' ? 'معايير التقرير' : 'Report criteria',
      })
      await expect(printedCriteria).toBeVisible()
      await expect(printedCriteria).toContainText(
        locale === 'ar' ? selected.name_ar : selected.name_en,
      )
      expect(await page.evaluate(() => getComputedStyle(document.body).backgroundColor)).toBe(
        'rgb(255, 255, 255)',
      )
      expect(
        await page.evaluate(() => getComputedStyle(document.documentElement).colorScheme),
      ).toBe('light')
      await expect(page.getByRole('table', { name: title, exact: true })).toBeVisible()
      await expect(page.getByRole('banner', { includeHidden: true })).toBeHidden()
      await expect(
        page.getByRole('combobox', {
          name: locale === 'ar' ? 'دورة القبول' : 'Admission Cycle',
          exact: true,
          includeHidden: true,
        }),
      ).toBeHidden()
      const printText = await page.getByRole('article', { name: title }).evaluate((article) => ({
        colors: [...article.querySelectorAll('p,h1,td,th,dt,dd')].map(
          (element) => getComputedStyle(element).color,
        ),
        axisColors: [...article.querySelectorAll('svg text')].map(
          (element) => getComputedStyle(element).fill,
        ),
        fits: [...article.querySelectorAll('table')].every(
          (table) =>
            table.getBoundingClientRect().width <= article.getBoundingClientRect().width + 1,
        ),
        direction: getComputedStyle(article).direction,
      }))
      expect(printText.colors.every((color) => color === 'rgb(0, 0, 0)')).toBe(true)
      expect(printText.axisColors.every((color) => color === 'rgb(0, 0, 0)')).toBe(true)
      expect(printText.fits).toBe(true)
      expect(printText.direction).toBe(locale === 'ar' ? 'rtl' : 'ltr')
      await page.screenshot({
        path: testInfo.outputPath(`pipeline-print-${account}-${locale}.png`),
        fullPage: true,
      })
      await page.pdf({
        path: testInfo.outputPath(`pipeline-print-${account}-${locale}.pdf`),
        printBackground: true,
        preferCSSPageSize: true,
      })
      await page.emulateMedia({ media: 'screen' })
      await page.setViewportSize({ width: 1280, height: 720 })
      await page
        .getByRole('button', { name: locale === 'ar' ? 'عرض كجدول' : 'View as table' })
        .click()
      const table = page.getByRole('table', { name: title, exact: true })
      await expect(table.getByRole('row')).toHaveCount(14)
      const stage = table.getByRole('link', {
        name: locale === 'ar' ? 'قيد المراجعة' : 'Under review',
        exact: true,
      })
      await expect(stage).toHaveAttribute(
        'href',
        `/screening?admission_cycle_id=${selected.id}&degree_level=MSc&status=under_sa_review`,
      )
      await expect(stage.locator('..').locator('..').getByRole('cell')).toHaveText(
        String(expectedCount),
      )
      const reportScan = await new AxeBuilder({ page }).analyze()
      expect(
        reportScan.violations.filter(
          (violation) => violation.impact === 'serious' || violation.impact === 'critical',
        ),
      ).toEqual([])
      await page.evaluate(() => {
        if (document.activeElement instanceof HTMLElement) document.activeElement.blur()
        window.scrollTo(0, 0)
      })
      await page.screenshot({
        path: testInfo.outputPath(`pipeline-${account}-${locale}.png`),
        fullPage: true,
      })
      const pendingList = page.waitForResponse(
        (response) =>
          response.url().includes('/api/v1/applications?') && response.request().method() === 'GET',
      )
      await stage.focus()
      await stage.press('Enter')
      const population = (await (await pendingList).json()).meta.total
      expect(population).toBe(expectedCount)
      await expect(page).toHaveURL(/status=under_sa_review/)
      await expect(
        page.getByRole('combobox', { name: locale === 'ar' ? 'حالة الطلب' : 'Application Status' }),
      ).toContainText(locale === 'ar' ? 'قيد المراجعة' : 'Under review')
      const pendingDownload = page.waitForEvent('download')
      await page
        .getByRole('button', {
          name: locale === 'ar' ? 'تصدير جميع الصفوف المطابقة' : 'Export all filtered rows',
        })
        .click()
      const download = await pendingDownload
      expect(download.suggestedFilename()).toBe('applications.xlsx')
      expect(await download.failure()).toBeNull()
      await download.saveAs(testInfo.outputPath(`pipeline-stage-${account}-${locale}.xlsx`))
      await page.goBack()
      await expect(page).toHaveURL(
        new RegExp(`application-pipeline\\?admission_cycle_id=${selected.id}`),
      )
      await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible()
      const clickList = page.waitForResponse(
        (response) =>
          response.url().includes('/api/v1/applications?') && response.request().method() === 'GET',
      )
      await page.getByRole('img', { name: title }).locator('.MuiBarChart-element').nth(3).click()
      expect((await (await clickList).json()).meta.total).toBe(expectedCount)
      await expect(page).toHaveURL(/status=under_sa_review/)
      await page.getByRole('button', { name: 'English' }).click()
    })
  }
}
