# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: catalog-detail-audit.spec.ts >> Catalog details curriculum history and dialogs work in light ar
- Location: e2e/catalog-detail-audit.spec.ts:10:5

# Error details

```
Test timeout of 120000ms exceeded.
```

```
Error: locator.click: Test timeout of 120000ms exceeded.
Call log:
  - waiting for getByRole('row').filter({ has: getByRole('cell', { name: 'CSE511', exact: true }) }).getByRole('link', { name: 'تعديل', exact: true })

```

# Test source

```ts
  1  | import {
  2  |   loginInEnglish as loginWithSavedPreference,
  3  |   selectAuthenticatedLanguage,
  4  | } from './authenticatedLanguage'
  5  | import { expect, test } from '@playwright/test'
  6  | import AxeBuilder from '@axe-core/playwright'
  7  |
  8  | for (const scheme of ['light', 'dark'] as const) {
  9  |   for (const locale of ['en', 'ar'] as const) {
  10 |     test(`Catalog details curriculum history and dialogs work in ${scheme} ${locale}`, async ({
  11 |       page,
  12 |     }) => {
  13 |       test.setTimeout(120_000)
  14 |       await page.addInitScript((mode) => localStorage.setItem('mui-mode', mode), scheme)
  15 |       await loginWithSavedPreference(page, 'r09@ejust.test')
  16 |       await selectAuthenticatedLanguage(page, locale)
  17 |       for (const [collection, code] of [
  18 |         ['programs', 'MSC-CE'],
  19 |         ['courses', 'CSE511'],
  20 |       ]) {
  21 |         await page.goto(`/catalog/${collection}?q=${code}`)
  22 |         const row = page
  23 |           .getByRole('row')
  24 |           .filter({ has: page.getByRole('cell', { name: code, exact: true }) })
  25 |         await row
  26 |           .getByRole('link', { name: locale === 'ar' ? 'تعديل' : 'Edit', exact: true })
> 27 |           .click()
     |            ^ Error: locator.click: Test timeout of 120000ms exceeded.
  28 |         await expect(page.getByTestId('version-history')).toBeVisible()
  29 |         await expect(page.getByTestId('version-history').getByRole('progressbar')).toHaveCount(0)
  30 |         if (collection === 'programs') {
  31 |           await expect(
  32 |             page.getByTestId('version-history').getByRole('listitem').first(),
  33 |           ).toBeVisible()
  34 |         } else {
  35 |           if ((await page.getByTestId('version-history').getByRole('listitem').count()) === 0) {
  36 |             await expect(page.getByTestId('version-history')).toContainText(
  37 |               locale === 'ar' ? 'لا توجد إصدارات بعد.' : 'No versions yet.',
  38 |             )
  39 |           }
  40 |           await expect(page.locator('main').getByRole('heading', { level: 6 })).toHaveCount(0)
  41 |         }
  42 |         await expect(page.getByTestId('version-history').getByRole('button')).toHaveCount(0)
  43 |         if (collection === 'programs')
  44 |           await expect(page.getByTestId('curriculum-section-ResearchElective')).toBeVisible()
  45 |         for (const width of [390, 1280]) {
  46 |           await page.setViewportSize({ width, height: 900 })
  47 |           expect(
  48 |             await page.evaluate(
  49 |               () =>
  50 |                 document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2,
  51 |             ),
  52 |             `${collection}: narrow/wide document fit`,
  53 |           ).toBe(true)
  54 |           const scan = await new AxeBuilder({ page }).analyze()
  55 |           expect(
  56 |             scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
  57 |           ).toEqual([])
  58 |         }
  59 |         await page
  60 |           .getByRole('button', { name: locale === 'ar' ? 'تعديل التفاصيل' : 'Edit details' })
  61 |           .click()
  62 |         const dialog = page.getByRole('dialog')
  63 |         await expect(dialog).toBeVisible()
  64 |         await dialog.evaluate(async (element) => {
  65 |           const animations: Animation[] = []
  66 |           for (let parent: Element | null = element; parent; parent = parent.parentElement) {
  67 |             animations.push(
  68 |               ...parent
  69 |                 .getAnimations()
  70 |                 .filter((animation) => animation.effect?.getTiming().iterations !== Infinity),
  71 |             )
  72 |           }
  73 |           await Promise.all(
  74 |             animations.map((animation) => animation.finished.catch(() => undefined)),
  75 |           )
  76 |         })
  77 |         expect(await dialog.evaluate((element) => element.contains(document.activeElement))).toBe(
  78 |           true,
  79 |         )
  80 |         const scan = await new AxeBuilder({ page }).analyze()
  81 |         expect(
  82 |           scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
  83 |         ).toEqual([])
  84 |         await dialog
  85 |           .getByRole('button', { name: locale === 'ar' ? 'إلغاء' : 'Cancel', exact: true })
  86 |           .click()
  87 |         await expect(dialog).toBeHidden()
  88 |       }
  89 |       await selectAuthenticatedLanguage(page, 'en')
  90 |     })
  91 |   }
  92 | }
  93 |
```
