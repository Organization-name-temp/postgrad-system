import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

for (const scheme of ['light', 'dark'] as const) {
  for (const locale of ['en', 'ar'] as const) {
    test(`Catalog details curriculum history and dialogs work in ${scheme} ${locale}`, async ({
      page,
    }) => {
      test.setTimeout(120_000)
      await page.addInitScript((mode) => localStorage.setItem('mui-mode', mode), scheme)
      await page.goto('/login')
      await page.getByLabel('Email').fill('r09@ejust.test')
      await page.getByLabel('Password').fill('password')
      await page.getByRole('button', { name: 'Sign in' }).click()
      await expect(page).toHaveURL(/\/$/)
      await page.getByRole('button', { name: locale === 'ar' ? 'العربية' : 'English' }).click()
      for (const [collection, code] of [
        ['programs', 'MSC-CE'],
        ['courses', 'CSE511'],
      ]) {
        await page.goto(`/catalog/${collection}?q=${code}`)
        const row = page
          .getByRole('row')
          .filter({ has: page.getByRole('cell', { name: code, exact: true }) })
        await row
          .getByRole('link', { name: locale === 'ar' ? 'تعديل' : 'Edit', exact: true })
          .click()
        await expect(page.getByTestId('version-history')).toBeVisible()
        await expect(page.getByTestId('version-history').getByRole('progressbar')).toHaveCount(0)
        if (collection === 'programs') {
          await expect(
            page.getByTestId('version-history').getByRole('listitem').first(),
          ).toBeVisible()
        } else {
          if ((await page.getByTestId('version-history').getByRole('listitem').count()) === 0) {
            await expect(page.getByTestId('version-history')).toContainText(
              locale === 'ar' ? 'لا توجد إصدارات بعد.' : 'No versions yet.',
            )
          }
          await expect(page.locator('main').getByRole('heading', { level: 6 })).toHaveCount(0)
        }
        await expect(page.getByTestId('version-history').getByRole('button')).toHaveCount(0)
        if (collection === 'programs')
          await expect(page.getByTestId('curriculum-section-ResearchElective')).toBeVisible()
        for (const width of [390, 1280]) {
          await page.setViewportSize({ width, height: 900 })
          expect(
            await page.evaluate(
              () =>
                document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2,
            ),
            `${collection}: narrow/wide document fit`,
          ).toBe(true)
          const scan = await new AxeBuilder({ page }).analyze()
          expect(
            scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
          ).toEqual([])
        }
        await page
          .getByRole('button', { name: locale === 'ar' ? 'تعديل التفاصيل' : 'Edit details' })
          .click()
        const dialog = page.getByRole('dialog')
        await expect(dialog).toBeVisible()
        await dialog.evaluate(async (element) => {
          const animations: Animation[] = []
          for (let parent: Element | null = element; parent; parent = parent.parentElement) {
            animations.push(
              ...parent
                .getAnimations()
                .filter((animation) => animation.effect?.getTiming().iterations !== Infinity),
            )
          }
          await Promise.all(
            animations.map((animation) => animation.finished.catch(() => undefined)),
          )
        })
        expect(await dialog.evaluate((element) => element.contains(document.activeElement))).toBe(
          true,
        )
        const scan = await new AxeBuilder({ page }).analyze()
        expect(
          scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
        ).toEqual([])
        await dialog
          .getByRole('button', { name: locale === 'ar' ? 'إلغاء' : 'Cancel', exact: true })
          .click()
        await expect(dialog).toBeHidden()
      }
      await page.getByRole('button', { name: 'English' }).click()
    })
  }
}
