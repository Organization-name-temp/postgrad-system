import { expect, test } from '@playwright/test'

test('dark theme prints all Transcript text legibly on white paper', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('mui-mode', 'dark'))
  await page.goto('/login')
  await page.getByLabel('Email').fill('student.smoke@seed.ejust.test')
  await page.getByLabel('Password').fill('password')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page).toHaveURL(/\/$/)
  await page.getByRole('button', { name: 'English', exact: true }).click()
  await page.goto('/records')
  await expect(page.getByText('31000000000004')).toBeVisible()
  await page.emulateMedia({ media: 'print' })
  const colors = await page
    .locator('main')
    .evaluate((main) =>
      [...main.querySelectorAll('p,h1,h2,h3,td,th')].map(
        (element) => getComputedStyle(element).color,
      ),
    )
  expect(colors.every((color) => color === 'rgb(0, 0, 0)')).toBe(true)
})
