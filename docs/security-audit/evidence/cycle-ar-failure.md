# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: cycle-collections.spec.ts >> Cycle criteria and authenticated workbook work in ar
- Location: e2e/cycle-collections.spec.ts:9:3

# Error details

```
Test timeout of 60000ms exceeded.
```

```
Error: locator.fill: Test timeout of 60000ms exceeded.
Call log:
  - waiting for getByLabel('Email')

```

# Test source

```ts
  1  | import { expect, type Page } from '@playwright/test'
  2  |
  3  | export async function selectAuthenticatedLanguage(page: Page, locale: 'en' | 'ar') {
  4  |   if ((await page.locator('html').getAttribute('lang')) !== locale) {
  5  |     const persisted = page.waitForResponse(async (response) => {
  6  |       if (
  7  |         new URL(response.url()).pathname !== '/api/v1/me' ||
  8  |         response.request().method() !== 'GET' ||
  9  |         response.status() !== 200
  10 |       )
  11 |         return false
  12 |       return (await response.json()).data.preferred_locale === locale
  13 |     })
  14 |     await page
  15 |       .getByRole('button', { name: locale === 'ar' ? 'العربية' : 'English', exact: true })
  16 |       .click()
  17 |     await persisted
  18 |   }
  19 |   await expect(page.locator('html')).toHaveAttribute('lang', locale)
  20 |   await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr')
  21 | }
  22 |
  23 | export async function loginInEnglish(page: Page, email: string) {
  24 |   await page.goto('/login')
> 25 |   await page.getByLabel('Email').fill(email)
     |                                  ^ Error: locator.fill: Test timeout of 60000ms exceeded.
  26 |   await page.getByLabel('Password').fill('password')
  27 |   const currentUser = page.waitForResponse(
  28 |     (response) =>
  29 |       new URL(response.url()).pathname === '/api/v1/me' &&
  30 |       response.request().method() === 'GET' &&
  31 |       response.status() === 200,
  32 |   )
  33 |   await page.getByRole('button', { name: 'Sign in' }).click()
  34 |   const user = (await (await currentUser).json()).data
  35 |   expect(user.email).toBe(email)
  36 |   await expect(page).toHaveURL(/\/$/)
  37 |   await expect(page.locator('html')).toHaveAttribute('lang', user.preferred_locale)
  38 |   await selectAuthenticatedLanguage(page, 'en')
  39 | }
  40 |
```
