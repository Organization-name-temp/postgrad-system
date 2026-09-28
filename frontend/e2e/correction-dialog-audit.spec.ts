import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { loginInEnglish, selectAuthenticatedLanguage } from './authenticatedLanguage'

for (const scheme of ['light', 'dark'] as const) {
  for (const locale of ['en', 'ar'] as const) {
    test(`Correction selection and validation remain usable in ${scheme} ${locale}`, async ({
      page,
    }) => {
      test.setTimeout(90_000)
      await page.addInitScript((mode) => localStorage.setItem('mui-mode', mode), scheme)
      await loginInEnglish(page, 'interviewer.hoda@ejust.test')
      await page.goto('/grading')
      await page
        .getByRole('row', { name: /CSE511/ })
        .filter({ hasText: 'Finalized' })
        .first()
        .getByRole('link')
        .click()
      await expect(
        page.getByRole('button', { name: 'Start correction round', exact: true }),
      ).toBeVisible()
      await selectAuthenticatedLanguage(page, locale)
      const start = locale === 'ar' ? 'بدء جولة تصحيح' : 'Start correction round'
      await page.getByRole('button', { name: start, exact: true }).click()
      const dialog = page.getByRole('dialog')
      await expect(dialog).toBeVisible()
      await expect(dialog.getByRole('checkbox').first()).toBeVisible()
      // The existing owner validates this without making a correction write.
      await dialog.getByRole('button', { name: start, exact: true }).click()
      await expect(dialog.getByRole('alert')).toBeVisible()
      for (const width of [390, 1280]) {
        await page.setViewportSize({ width, height: 900 })
        const scan = await new AxeBuilder({ page }).analyze()
        expect(
          scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
        ).toEqual([])
      }
      await dialog.getByRole('checkbox').first().focus()
      await dialog.getByRole('checkbox').first().press('Space')
      await expect(dialog.getByRole('checkbox').first()).toBeChecked()
      await dialog
        .getByRole('button', { name: locale === 'ar' ? 'إلغاء' : 'Cancel', exact: true })
        .click()
      await expect(dialog).toBeHidden()
      await selectAuthenticatedLanguage(page, 'en')
    })
  }
}
