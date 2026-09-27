import {
  loginInEnglish as loginWithSavedPreference,
  selectAuthenticatedLanguage,
} from './authenticatedLanguage'
import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const bands = {
  ready: { en: 'Academically ready (advisory)', ar: 'جاهز أكاديميًا (استرشادي)' },
  not_ready: { en: 'Not academically ready', ar: 'غير جاهز أكاديميًا' },
  unavailable: { en: 'Readiness unavailable', ar: 'جاهزية التخرج غير متاحة' },
}

for (const account of ['r05', 'r07']) {
  for (const locale of ['en', 'ar'] as const) {
    test(`Readiness Report scoped drill export and paper work for ${account} in ${locale}`, async ({
      page,
    }, testInfo) => {
      await page.addInitScript(
        (mode) => localStorage.setItem('mui-mode', mode),
        account === 'r07' ? 'dark' : 'light',
      )
      await loginWithSavedPreference(page, `${account}@ejust.test`)
      await selectAuthenticatedLanguage(page, locale)
      const title = locale === 'ar' ? 'تقرير جاهزية التخرج' : 'Graduation Readiness Report'
      await expect(page.getByRole('link', { name: title, exact: true })).toBeVisible()
      const headers = { Accept: 'application/json', Referer: page.url() }
      const candidates = await page.request.get(
        '/api/v1/academic-standing/reports/graduation-readiness/students?per_page=100',
        { headers },
      )
      expect(candidates.ok()).toBeTruthy()
      const candidate = (await candidates.json()).data.find(
        (row: { program: unknown }) => row.program !== null,
      ) as
        | {
            program: { id: number; name_en: string; name_ar: string; degree_level: string }
          }
        | undefined
      expect(candidate, 'The seeded persona needs a visible academic cohort').toBeDefined()
      if (!candidate) throw new Error('No seeded academic cohort')
      const criteria = `program_id=${candidate.program.id}&degree_level=${candidate.program.degree_level}&blocker=RemainingRequirements`
      const pendingReport = page.waitForResponse(
        (response) =>
          response.url().includes('/api/v1/academic-standing/reports/graduation-readiness?') &&
          response.request().method() === 'GET',
      )
      await page.goto(`/analytics/graduation-readiness?${criteria}`)
      const summary = (await (await pendingReport).json()).data as {
        total: number
        groups: Record<keyof typeof bands, number>
        blockers: Record<string, number>
      }
      await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible()
      await expect(
        page.getByRole('combobox', { name: locale === 'ar' ? 'البرنامج' : 'Program', exact: true }),
      ).toHaveValue(locale === 'ar' ? candidate.program.name_ar : candidate.program.name_en)
      const chartTitle = locale === 'ar' ? 'فئات الجاهزية الاسترشادية' : 'Advisory readiness groups'
      const table = page.getByRole('table', { name: chartTitle, exact: true })
      await page.setViewportSize({ width: 794, height: 1123 })
      await page.emulateMedia({ media: 'print' })
      const printedCriteria = page.getByRole('region', {
        name: locale === 'ar' ? 'معايير التقرير' : 'Report criteria',
      })
      await expect(printedCriteria).toBeVisible()
      await expect(printedCriteria).toContainText(
        locale === 'ar' ? candidate.program.name_ar : candidate.program.name_en,
      )
      await expect(printedCriteria).toContainText(locale === 'ar' ? 'نشط' : 'Active')
      await expect(table).toBeVisible()
      await expect(page.getByRole('banner', { includeHidden: true })).toBeHidden()
      expect(
        await page.evaluate(() => getComputedStyle(document.documentElement).colorScheme),
      ).toBe('light')
      const paper = await page.getByRole('article', { name: title }).evaluate((article) => ({
        direction: getComputedStyle(article).direction,
        colors: [...article.querySelectorAll('h1,td,th,dt,dd')].map(
          (element) => getComputedStyle(element).color,
        ),
        axisColors: [...article.querySelectorAll('svg text')].map(
          (element) => getComputedStyle(element).fill,
        ),
        fits: [...article.querySelectorAll('table')].every(
          (element) =>
            element.getBoundingClientRect().width <= article.getBoundingClientRect().width + 1,
        ),
      }))
      expect(paper.direction).toBe(locale === 'ar' ? 'rtl' : 'ltr')
      expect(paper.colors.every((color) => color === 'rgb(0, 0, 0)')).toBe(true)
      expect(paper.axisColors.every((color) => color === 'rgb(0, 0, 0)')).toBe(true)
      expect(paper.fits).toBe(true)
      await page.pdf({
        path: testInfo.outputPath(`readiness-print-${account}-${locale}.pdf`),
        printBackground: true,
        preferCSSPageSize: true,
      })
      await page.screenshot({
        path: testInfo.outputPath(`readiness-print-${account}-${locale}.png`),
        fullPage: true,
      })
      await page.emulateMedia({ media: 'screen' })
      await page.setViewportSize({ width: 1280, height: 720 })
      await page
        .getByRole('region', { name: chartTitle, exact: true })
        .getByRole('button', { name: locale === 'ar' ? 'عرض كجدول' : 'View as table' })
        .click()
      await expect(table.getByRole('row')).toHaveCount(4)
      for (const [band, labels] of Object.entries(bands)) {
        const count = summary.groups[band as keyof typeof bands]
        const row = table
          .getByRole('row')
          .filter({ has: page.getByRole('rowheader', { name: labels[locale], exact: true }) })
        await expect(row.getByRole('cell')).toHaveText(String(count))
        if (count === 0) await expect(row.getByRole('link')).toHaveCount(0)
      }
      const blockerLabels = {
        RemainingRequirements:
          locale === 'ar' ? 'متطلبات خطة دراسية متبقية' : 'Remaining curriculum requirements',
        CgpaUnavailable: locale === 'ar' ? 'المعدل التراكمي غير متاح' : 'CGPA unavailable',
        CgpaBelowMinimum:
          locale === 'ar' ? 'المعدل التراكمي أقل من الحد الأدنى' : 'CGPA below minimum',
        TerminationPending:
          locale === 'ar'
            ? 'حالة إنهاء قيد تستدعي الإجراء قيد النظر'
            : 'Actionable termination Case pending',
      }
      const blockerTitle = locale === 'ar' ? 'العوائق (متداخلة)' : 'Blockers (overlapping)'
      await page
        .getByRole('region', { name: blockerTitle, exact: true })
        .getByRole('button', { name: locale === 'ar' ? 'عرض كجدول' : 'View as table' })
        .click()
      const blockerTable = page.getByRole('table', { name: blockerTitle, exact: true })
      await expect(blockerTable.getByRole('row')).toHaveCount(5)
      for (const [blocker, label] of Object.entries(blockerLabels)) {
        const count = summary.blockers[blocker]
        const row = blockerTable
          .getByRole('row')
          .filter({ has: page.getByRole('rowheader', { name: label, exact: true }) })
        await expect(row.getByRole('cell')).toHaveText(String(count))
        if (count === 0) await expect(row.getByRole('link')).toHaveCount(0)
        else {
          await expect(row.getByRole('link')).toHaveAttribute(
            'href',
            `/analytics/graduation-readiness/students?${criteria}&blocker_all=${blocker}`,
          )
          const matching = await page.request.get(
            `/api/v1/academic-standing/reports/graduation-readiness/students?${criteria}&blocker_all=${blocker}`,
            { headers: { ...headers, Referer: page.url() } },
          )
          expect(matching.ok()).toBeTruthy()
          expect((await matching.json()).meta.total).toBe(count)
        }
      }
      const scan = await new AxeBuilder({ page }).analyze()
      expect(
        scan.violations.filter(
          (violation) => violation.impact === 'serious' || violation.impact === 'critical',
        ),
      ).toEqual([])
      const selected = (Object.keys(bands) as (keyof typeof bands)[]).find(
        (band) => summary.groups[band] > 0,
      )
      if (!selected) throw new Error('No selected readiness group')
      const bandLink = table.getByRole('link', { name: bands[selected][locale], exact: true })
      await expect(bandLink).toHaveAttribute(
        'href',
        `/analytics/graduation-readiness/students?${criteria}&readiness=${selected}`,
      )
      const pendingList = page.waitForResponse(
        (response) =>
          response
            .url()
            .includes('/api/v1/academic-standing/reports/graduation-readiness/students?') &&
          response.request().method() === 'GET',
      )
      await bandLink.focus()
      await bandLink.press('Enter')
      const students = await (await pendingList).json()
      expect(students.meta.total).toBe(summary.groups[selected])
      expect(JSON.stringify(students)).not.toContain('national_id')
      await expect(page).toHaveURL(new RegExp(`readiness=${selected}`))
      const rowTable = page.getByRole('table', {
        name:
          locale === 'ar'
            ? 'الطلاب المطابقون والجاهزية الاسترشادية'
            : 'Matching Students and advisory readiness',
      })
      await expect(rowTable).toBeVisible()
      await expect(rowTable.getByRole('row')).toHaveCount(students.data.length + 1)
      const first = students.data[0] as {
        student_id: number
        university_id: string
        readiness: { cgpa: number | null } | null
      }
      const firstRow = rowTable.getByRole('row', { name: new RegExp(first.university_id) })
      await expect(
        firstRow.getByRole('cell', {
          name:
            first.readiness?.cgpa == null
              ? locale === 'ar'
                ? 'المعدل التراكمي غير متاح'
                : 'CGPA unavailable'
              : first.readiness?.cgpa.toFixed(2),
          exact: true,
        }),
      ).toBeVisible()
      const details = await page.request.get(`/api/v1/grading/students/${first.student_id}`, {
        headers: { ...headers, Referer: page.url() },
      })
      expect(details.ok()).toBeTruthy()
      const pendingDownload = page.waitForEvent('download')
      await page
        .getByRole('button', {
          name: locale === 'ar' ? 'تصدير جميع الصفوف المطابقة' : 'Export all filtered rows',
        })
        .click()
      const download = await pendingDownload
      expect(download.suggestedFilename()).toBe('graduation-readiness-students.xlsx')
      expect(await download.failure()).toBeNull()
      await download.saveAs(testInfo.outputPath(`readiness-students-${account}-${locale}.xlsx`))
      const listScan = await new AxeBuilder({ page }).analyze()
      expect(
        listScan.violations.filter(
          (violation) => violation.impact === 'serious' || violation.impact === 'critical',
        ),
      ).toEqual([])
      await page.screenshot({
        path: testInfo.outputPath(`readiness-students-${account}-${locale}.png`),
        fullPage: true,
      })
      await page.goBack()
      await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible()
      await selectAuthenticatedLanguage(page, 'en')
    })
  }
}
