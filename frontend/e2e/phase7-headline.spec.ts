import { loginInEnglish, selectAuthenticatedLanguage } from './authenticatedLanguage'
import { execFileSync } from 'node:child_process'
import { expect, test } from '@playwright/test'

function workbook(path: string): (string | number | null)[][] {
  const script = String.raw`
require '../backend/vendor/autoload.php';
$reader = new OpenSpout\Reader\XLSX\Reader;
$reader->open($argv[1]);
$rows = [];
foreach ($reader->getSheetIterator() as $sheet) {
    foreach ($sheet->getRowIterator() as $row) $rows[] = $row->toArray();
}
$reader->close();
echo json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
`
  return JSON.parse(execFileSync('php', ['-r', script, path], { encoding: 'utf8' }))
}

test('Phase 7 headline: scoped academic populations, canonical exports, language continuity and authorized investigation', async ({
  page,
}, testInfo) => {
  test.setTimeout(180_000)
  await loginInEnglish(page, 'r03@ejust.test')
  const reports = [
    {
      route: 'academic-standing',
      api: 'standing',
      title: 'Academic Standing Report',
      table: 'Current Standing distribution',
      label: 'Termination Pending',
      field: 'standing',
      value: 'TerminationPending',
      list: 'students',
      filename: 'academic-standing-students.xlsx',
      idColumn: 0,
    },
    {
      route: 'graduation-readiness',
      api: 'graduation-readiness',
      title: 'Graduation Readiness Report',
      table: 'Advisory readiness groups',
      label: 'Not academically ready',
      field: 'readiness',
      value: 'not_ready',
      list: 'students',
      filename: 'graduation-readiness-students.xlsx',
      idColumn: 0,
    },
    {
      route: 'termination',
      api: 'termination',
      title: 'Termination Report',
      table: 'Case stages and outcomes',
      label: 'Flagged: awaiting Advisor assessment',
      field: 'state',
      value: 'AwaitingAdvisor',
      list: 'cases',
      filename: 'termination-report-cases.xlsx',
      idColumn: 1,
    },
  ]
  let membership: number[] | undefined
  for (const report of reports) {
    const criteria = 'q=Phase+7+report'
    const summaryResponse = page.waitForResponse(
      (r) =>
        r.url().includes(`/api/v1/academic-standing/reports/${report.api}?`) &&
        r.request().method() === 'GET',
    )
    await page.goto(`/analytics/${report.route}?${criteria}`)
    const summary = (await (await summaryResponse).json()).data
    expect(report.api === 'termination' ? summary.case_count : summary.total).toBe(3)
    if (report.api === 'termination') expect(summary.student_count).toBe(3)
    await expect(page.getByRole('heading', { name: report.title, exact: true })).toBeVisible()
    await selectAuthenticatedLanguage(page, 'ar')
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
    await expect(page).toHaveURL(new RegExp(`${criteria.replaceAll('+', '\\+')}$`))
    await expect(page.getByRole('textbox').first()).toHaveValue('Phase 7 report')
    await selectAuthenticatedLanguage(page, 'en')
    await expect(page.getByRole('heading', { name: report.title, exact: true })).toBeVisible()
    await page
      .getByRole('region', { name: report.table, exact: true })
      .getByRole('button', { name: 'View as table' })
      .click()
    const link = page
      .getByRole('table', { name: report.table, exact: true })
      .getByRole('link', { name: report.label, exact: true })
    const rowsResponse = page.waitForResponse(
      (r) =>
        r.url().includes(`/reports/${report.api}/${report.list}?`) &&
        r.request().method() === 'GET',
    )
    await link.focus()
    await link.press('Enter')
    const rows = await (await rowsResponse).json()
    expect(rows.meta.total).toBe(3)
    const ids = rows.data
      .map((row: { student_id: number }) => row.student_id)
      .sort((a: number, b: number) => a - b)
    if (membership) expect(ids).toEqual(membership)
    else membership = ids
    await expect(page).toHaveURL(new RegExp(`${report.field}=${report.value}`))
    const pendingDownload = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Export all filtered rows' }).click()
    const download = await pendingDownload
    expect(download.suggestedFilename()).toBe(report.filename)
    const path = testInfo.outputPath(report.filename)
    await download.saveAs(path)
    const exported = workbook(path)
    expect(exported).toHaveLength(4)
    expect(
      exported
        .slice(1)
        .map((row) => row[report.idColumn])
        .sort(),
    ).toEqual(ids.map(String).sort())
    if (report.api === 'termination') {
      await page.getByRole('link', { name: 'Open authorized review workflow' }).first().click()
      await expect(page).toHaveURL(/\/termination\/advisor$/)
      await expect(
        page.getByRole('heading', { name: `Student #${ids[0]}`, exact: true }),
      ).toBeVisible()
    } else {
      const detail = page.locator(`a[href="/grading/students/${ids[0]}"]`).first()
      await expect(detail).toBeVisible()
      const detailResponse = page.waitForResponse(
        (r) =>
          r.url().includes(`/api/v1/grading/students/${ids[0]}`) && r.request().method() === 'GET',
      )
      await detail.click()
      expect((await detailResponse).status()).toBe(200)
      await expect(page).toHaveURL(new RegExp(`/grading/students/${ids[0]}$`))
    }
  }
})
