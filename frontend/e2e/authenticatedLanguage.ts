import { expect, type Page } from '@playwright/test'

export async function selectAuthenticatedLanguage(page: Page, locale: 'en' | 'ar') {
  if ((await page.locator('html').getAttribute('lang')) !== locale) {
    const persisted = page.waitForResponse(async (response) => {
      if (
        new URL(response.url()).pathname !== '/api/v1/me' ||
        response.request().method() !== 'GET' ||
        response.status() !== 200
      )
        return false
      return (await response.json()).data.preferred_locale === locale
    })
    await page
      .getByRole('button', { name: locale === 'ar' ? 'العربية' : 'English', exact: true })
      .click()
    await persisted
  }
  await expect(page.locator('html')).toHaveAttribute('lang', locale)
  await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr')
}

export async function loginInEnglish(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill('password')
  const currentUser = page.waitForResponse(
    (response) =>
      new URL(response.url()).pathname === '/api/v1/me' &&
      response.request().method() === 'GET' &&
      response.status() === 200,
  )
  await page.getByRole('button', { name: 'Sign in' }).click()
  const user = (await (await currentUser).json()).data
  expect(user.email).toBe(email)
  await expect(page).toHaveURL(/\/$/)
  await expect(page.locator('html')).toHaveAttribute('lang', user.preferred_locale)
  await selectAuthenticatedLanguage(page, 'en')
}
