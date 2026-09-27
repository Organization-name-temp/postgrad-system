import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'
import type { GetApiV1RegistrationCapacityOverrides200DataItem } from '../src/shared/api/generated/endpoints'
import { loginInEnglish, selectAuthenticatedLanguage } from './authenticatedLanguage'

// The seed has no pending capacity requirement. Reuse the owner's existing interaction
// fixture for presentation only; CapacityOverrideTest proves server scope/transitions.
const requirement: GetApiV1RegistrationCapacityOverrides200DataItem = {
  id: 4,
  registration_request_id: 8,
  course_offering_id: 12,
  course_id: 3,
  department_id: 2,
  state: 'Pending',
  reason: null,
}

for (const scheme of ['light', 'dark'] as const) {
  for (const locale of ['en', 'ar'] as const) {
    test(`populated capacity validation and denial dialog in ${scheme} ${locale}`, async ({
      page,
    }, testInfo) => {
      test.setTimeout(120_000)
      await page.addInitScript((mode) => localStorage.setItem('mui-mode', mode), scheme)
      await loginInEnglish(page, 'r06@ejust.test')
      await selectAuthenticatedLanguage(page, locale)
      const endpoint = '/api/v1/registration/capacity-overrides'
      // Authenticate against the real backend before supplying a populated read fixture.
      const realQueue = await page.request.get(endpoint, {
        headers: { Accept: 'application/json', Referer: page.url() },
      })
      expect(realQueue.status()).toBe(200)
      const writes: string[] = []
      page.on('request', (request) => {
        if (request.url().includes(endpoint) && request.method() !== 'GET') {
          writes.push(request.method())
        }
      })
      await page.route(`**${endpoint}`, async (route) => {
        if (route.request().method() === 'GET') {
          await route.fulfill({ json: { data: [requirement] } })
        } else {
          await route.continue()
        }
      })
      await page.goto('/registration/capacity-overrides')
      await expect(
        page.getByRole('heading', {
          name: locale === 'ar' ? 'المقرر #3' : 'Course #3',
          level: 2,
        }),
      ).toBeVisible()
      await page
        .getByRole('button', {
          name: locale === 'ar' ? 'الموافقة على الاستثناء' : 'Approve exception',
          exact: true,
        })
        .click()
      await expect(
        page.getByText(locale === 'ar' ? 'أدخل سبب القرار.' : 'Enter a decision reason.', {
          exact: true,
        }),
      ).toBeVisible()
      const reason = page.getByRole('textbox', {
        name: locale === 'ar' ? 'سبب القرار' : 'Decision reason',
      })
      await reason.fill('Capacity #8 — سبب القرار')
      const deny = locale === 'ar' ? 'رفض الاستثناء' : 'Deny exception'
      await page.getByRole('button', { name: deny, exact: true }).click()
      const dialog = page.getByRole('dialog', { name: deny, exact: true })
      await expect(dialog).toBeVisible()
      await dialog.evaluate(async (element) => {
        const animations: Animation[] = []
        for (let node: Element | null = element; node; node = node.parentElement) {
          animations.push(...node.getAnimations())
        }
        await Promise.all(animations.map((animation) => animation.finished.catch(() => undefined)))
      })
      await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr')
      await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)
      for (const width of [390, 1280]) {
        await page.setViewportSize({ width, height: 900 })
        expect(
          await page.evaluate(
            () => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2,
          ),
        ).toBe(true)
        const { violations } = await new AxeBuilder({ page }).analyze()
        expect(violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')).toEqual(
          [],
        )
      }
      await testInfo.attach(`capacity-denial-${scheme}-${locale}`, {
        body: await page.screenshot({ fullPage: true }),
        contentType: 'image/png',
      })
      const cancel = dialog.getByRole('button', {
        name: locale === 'ar' ? 'إلغاء' : 'Cancel',
        exact: true,
      })
      await cancel.focus()
      await cancel.press('Enter')
      await expect(dialog).toBeHidden()
      await expect(reason).toHaveValue('Capacity #8 — سبب القرار')
      expect(writes).toEqual([])
      await selectAuthenticatedLanguage(page, 'en')
    })
  }
}
