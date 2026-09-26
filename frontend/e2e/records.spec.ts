import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page, type TestInfo } from '@playwright/test'

async function login(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/$/)
  await page.getByRole('button', { name: 'English', exact: true }).click()
}
async function accessible(page: Page) {
  const { violations } = await new AxeBuilder({ page }).analyze()
  expect(violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')).toEqual([])
}
async function submitRequest(page: Page, delivery: 'digital' | 'collection') {
  await page.goto('/records/requests')
  await page.getByLabel('Delivery method').click()
  await page
    .getByRole('option', {
      name: delivery === 'digital' ? 'Digital access' : 'Collection from Student Affairs',
      exact: true,
    })
    .click()
  const response = page.waitForResponse(
    (response) =>
      response.url().endsWith('/api/v1/records/requests') && response.request().method() === 'POST',
  )
  await page.getByRole('button', { name: 'Submit request', exact: true }).click()
  const result = await response
  expect(result.status()).toBe(201)
  const { data } = await result.json()
  return data.id as number
}
async function open(page: Page, id: number) {
  await page.goto(`/records/requests/${id}`)
}
async function reasonAction(page: Page, button: string, reason: string) {
  await page.getByLabel('Reason', { exact: true }).fill(reason)
  await page.getByRole('button', { name: button, exact: true }).click()
}
async function evidence(page: Page, button: string, reference: string) {
  await page.getByLabel('Payment or refund reference').fill(reference)
  await page.getByLabel('Receipt (PDF, JPEG or PNG; up to 10 MB)').setInputFiles({
    name: 'learning-receipt.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from(
      '%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF',
    ),
  })
  await page.getByRole('button', { name: button, exact: true }).click()
}
async function approveAndPay(student: Page, staff: Page, id: number) {
  await open(staff, id)
  await reasonAction(staff, 'Approve request', 'Fictional learning identity reviewed')
  await expect(staff.getByText('Captured payment instructions')).toBeVisible()
  await open(student, id)
  await evidence(student, 'Submit payment evidence', `LEARNING-PAY-${id}`)
  await expect(student.getByText('Payment under review', { exact: false }).first()).toBeVisible()
  await open(staff, id)
  await staff.getByLabel('Verified payment or full refund (EGP)').fill('125.50')
  await staff.getByRole('button', { name: 'Verify payment', exact: true }).click()
  await expect(
    staff.getByRole('button', { name: 'Review complete official preview' }),
  ).toBeVisible()
}
async function issue(staff: Page, id: number) {
  await open(staff, id)
  await staff.getByRole('button', { name: 'Review complete official preview' }).click()
  await expect(staff.getByRole('checkbox')).toBeVisible()
  await expect(staff.locator('article[dir="ltr"]')).toBeVisible()
  await expect(staff.locator('article[dir="rtl"]')).toBeVisible()
  await accessible(staff)
  await staff.getByRole('checkbox').check()
  await staff.getByRole('button', { name: 'Certify this reviewed copy' }).click()
  await staff.getByRole('link', { name: 'Official Transcript', exact: true }).click()
  await expect(staff.getByRole('link', { name: 'Verify this official copy' })).toBeVisible()
}

async function checkReadAndPrintMatrix(
  page: Page,
  name: string,
  publicView: boolean,
  testInfo: TestInfo,
) {
  for (const scheme of ['light', 'dark'] as const) {
    for (const language of ['en', 'ar'] as const) {
      await page.evaluate((mode) => localStorage.setItem('mui-mode', mode), scheme)
      await page.reload()
      if (publicView) {
        if ((await page.locator('html').getAttribute('lang')) !== language) {
          await page
            .getByRole('button', { name: language === 'ar' ? 'العربية' : 'English', exact: true })
            .click()
        }
      } else {
        await page
          .getByRole('button', { name: language === 'ar' ? 'العربية' : 'English', exact: true })
          .click()
      }
      await expect(page.locator('html')).toHaveAttribute('dir', language === 'ar' ? 'rtl' : 'ltr')
      await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)
      await expect(page.getByText('31000000000004')).toBeVisible()
      await accessible(page)
      await page.emulateMedia({ media: 'print' })
      if (!publicView) await expect(page.getByRole('banner')).toBeHidden()
      await expect(page.locator('article')).toBeVisible()
      const fits = await page
        .locator('article')
        .evaluate((article) =>
          [...article.querySelectorAll('table')].every(
            (table) =>
              table.getBoundingClientRect().width <= article.getBoundingClientRect().width + 1,
          ),
        )
      expect(fits, 'Every printed transcript table must fit its document width').toBe(true)
      const colors = await page
        .locator('main')
        .evaluate((main) =>
          [...main.querySelectorAll('p,h1,h2,h3,td,th')].map(
            (element) => getComputedStyle(element).color,
          ),
        )
      expect(
        colors.every((color) => color === 'rgb(0, 0, 0)'),
        'Dark theme must not print faint or white Transcript text',
      ).toBe(true)
      await testInfo.attach(`${name}-print-${scheme}-${language}`, {
        body: await page.screenshot({ fullPage: true }),
        contentType: 'image/png',
      })
      await page.emulateMedia({ media: 'screen' })
    }
  }
}

test('Phase 6 serial journey: payment correction, bilingual certification/QR, revocation, full refund and collection', async ({
  browser,
}, testInfo) => {
  test.setTimeout(480_000)
  const studentContext = await browser.newContext()
  const staffContext = await browser.newContext()
  const recipientContext = await browser.newContext()
  const student = await studentContext.newPage()
  const staff = await staffContext.newPage()
  const recipient = await recipientContext.newPage()
  try {
    await login(student, 'student.smoke@seed.ejust.test')
    await login(staff, 'r05@ejust.test')
    await staff.goto('/records/fee')
    await staff.getByLabel('Fee (EGP)').fill('125.50')
    await staff
      .getByLabel('Payment instructions (English)')
      .fill('FICTIONAL LEARNING BANK ONLY — not an institutional payment destination.')
    await staff
      .getByLabel('Payment instructions (Arabic)')
      .fill('بنك تعليمي وهمي فقط — ليس جهة دفع جامعية.')
    await staff.getByRole('button', { name: 'Save fee configuration' }).click()
    await expect(staff.getByText('Fee configuration saved.')).toBeVisible()
    await accessible(staff)

    const digital = await submitRequest(student, 'digital')
    await open(staff, digital)
    await reasonAction(staff, 'Approve request', 'Learning approval')
    await open(student, digital)
    await evidence(student, 'Submit payment evidence', 'LEARNING-WRONG-RECEIPT')
    await expect(student.getByText('Payment under review', { exact: false }).first()).toBeVisible()
    await open(staff, digital)
    await reasonAction(staff, 'Return payment for correction', 'Upload corrected receipt')
    await open(student, digital)
    await expect(student.getByText('Upload corrected receipt').first()).toBeVisible()
    await evidence(student, 'Submit payment evidence', 'LEARNING-CORRECTED')
    await expect(student.getByText('Payment under review', { exact: false }).first()).toBeVisible()
    await open(staff, digital)
    await staff.getByLabel('Verified payment or full refund (EGP)').fill('125.50')
    await staff.getByRole('button', { name: 'Verify payment', exact: true }).click()
    await expect(
      staff.getByRole('button', { name: 'Review complete official preview' }),
    ).toBeVisible()
    await issue(staff, digital)
    const officialUrl = staff.url()
    const verificationUrl = await staff
      .getByRole('link', { name: 'Verify this official copy' })
      .getAttribute('href')
    expect(verificationUrl).toContain('/records/verify/')
    await recipient.goto(verificationUrl!)
    await expect(recipient.getByText('31000000000004')).toBeVisible()
    await expect(recipient.getByRole('navigation')).toHaveCount(0)
    await accessible(recipient)
    await checkReadAndPrintMatrix(recipient, 'public-valid', true, testInfo)
    await recipient.getByRole('button', { name: 'English', exact: true }).click()
    await student.goto('/records')
    await checkReadAndPrintMatrix(student, 'current', false, testInfo)
    await student.getByRole('button', { name: 'English', exact: true }).click()
    await checkReadAndPrintMatrix(staff, 'official', false, testInfo)
    await staff.getByRole('button', { name: 'English', exact: true }).click()
    await staff.getByLabel('Revocation reason').fill('Fictional learning revocation')
    await staff.getByRole('button', { name: 'Revoke validity' }).click()
    await expect(staff.getByText('Revoked official copy')).toBeVisible()
    await recipient.getByRole('button', { name: 'Refresh', exact: true }).click()
    await expect(recipient.getByText('Revoked official copy')).toBeVisible()
    await expect(recipient.getByText('31000000000004')).toHaveCount(0)
    await recipient.getByRole('button', { name: 'العربية', exact: true }).click()
    await expect(recipient.getByText('نسخة رسمية ملغاة الصلاحية')).toBeVisible()
    await expect(recipient.locator('article')).toHaveCount(0)
    await accessible(recipient)
    await recipient.emulateMedia({ media: 'print' })
    await expect(recipient.getByText('نسخة رسمية ملغاة الصلاحية')).toBeVisible()
    await expect(recipient.locator('article')).toHaveCount(0)
    await testInfo.attach('public-revoked-print-ar', {
      body: await recipient.screenshot({ fullPage: true }),
      contentType: 'image/png',
    })
    await recipient.emulateMedia({ media: 'screen' })

    const refund = await submitRequest(student, 'digital')
    await approveAndPay(student, staff, refund)
    await open(student, refund)
    await reasonAction(student, 'Request cancellation', 'Learning cancellation')
    await open(staff, refund)
    await reasonAction(staff, 'Approve cancellation', 'Full learning refund due')
    await expect(staff.getByRole('button', { name: 'Record full refund' })).toBeVisible()
    await staff.getByLabel('Verified payment or full refund (EGP)').fill('125.50')
    await evidence(staff, 'Record full refund', 'LEARNING-REFUND')
    await expect(staff.getByText('Cancelled', { exact: false }).first()).toBeVisible()

    const collection = await submitRequest(student, 'collection')
    await approveAndPay(student, staff, collection)
    await issue(staff, collection)
    await open(staff, collection)
    await staff.getByRole('button', { name: 'Confirm collection' }).click()
    await expect(staff.getByText('Completed', { exact: false }).first()).toBeVisible()
    await open(student, collection)
    await expect(
      student.getByRole('link', { name: 'Official Transcript', exact: true }),
    ).toBeVisible()
    await student.goto('/records/history')
    await expect(
      student.getByRole('heading', { name: 'Official copies', exact: true }),
    ).toBeVisible()
    await expect(student.getByText('Revoked', { exact: false }).first()).toBeVisible()
    await staff.goto(officialUrl)
    await expect(staff.getByText('Revoked official copy')).toBeVisible()
    await expect(staff.getByText('31000000000004')).toBeVisible()
  } finally {
    await studentContext.close()
    await staffContext.close()
    await recipientContext.close()
  }
})
