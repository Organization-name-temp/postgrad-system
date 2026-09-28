# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: dean-collections.spec.ts >> Dean collections bookmark and authenticated download work in ar
- Location: e2e/dean-collections.spec.ts:9:3

# Error details

```
Error: frame.evaluate: Execution context was destroyed, most likely because of a navigation.
```

# Test source

```ts
  1  | import {
  2  |   loginInEnglish as loginWithSavedPreference,
  3  |   selectAuthenticatedLanguage,
  4  | } from './authenticatedLanguage'
  5  | import { test, expect } from '@playwright/test'
  6  | import AxeBuilder from '@axe-core/playwright'
  7  |
  8  | for (const locale of ['en', 'ar'] as const) {
  9  |   test(`Dean collections bookmark and authenticated download work in ${locale}`, async ({
  10 |     page,
  11 |   }, testInfo) => {
  12 |     await loginWithSavedPreference(page, 'r07@ejust.test')
  13 |     await selectAuthenticatedLanguage(page, locale)
  14 |     for (const [path, resource] of [
  15 |       ['/dean', 'dean-review'],
  16 |       ['/dean/decisions', 'dean-decisions'],
  17 |     ] as const) {
  18 |       await page.goto(
  19 |         `${path}?q=NoSuchPhase7Candidate&faculty_id=1,2&degree_level=MSc&sort=-age_days${path.endsWith('decisions') ? '&status=waitlisted' : ''}`,
  20 |       )
  21 |       const search = page.getByLabel(locale === 'ar' ? 'البحث عن المتقدمين' : 'Search applicants')
  22 |       await expect(search).toHaveValue('NoSuchPhase7Candidate')
  23 |       const faculty = page.getByLabel(locale === 'ar' ? 'معرّفات الكليات' : 'Faculty IDs')
  24 |       await expect(faculty).toHaveValue('1,2')
  25 |       await page.reload()
  26 |       await expect(search).toHaveValue('NoSuchPhase7Candidate')
  27 |       await expect(faculty).toHaveValue('1,2')
  28 |       const pending = page.waitForEvent('download')
  29 |       await page
  30 |         .getByRole('button', {
  31 |           name: locale === 'ar' ? 'تصدير جميع الصفوف المطابقة' : 'Export all filtered rows',
  32 |         })
  33 |         .click()
  34 |       const download = await pending
  35 |       expect(download.suggestedFilename()).toBe('applications.xlsx')
  36 |       expect(await download.failure()).toBeNull()
  37 |       await download.saveAs(testInfo.outputPath(`${resource}-${locale}.xlsx`))
> 38 |       const scan = await new AxeBuilder({ page }).analyze()
     |                    ^ Error: frame.evaluate: Execution context was destroyed, most likely because of a navigation.
  39 |       expect(
  40 |         scan.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical'),
  41 |       ).toEqual([])
  42 |     }
  43 |     await selectAuthenticatedLanguage(page, 'en')
  44 |   })
  45 | }
  46 |
```
