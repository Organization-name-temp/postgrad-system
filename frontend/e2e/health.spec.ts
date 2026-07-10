import { test, expect } from '@playwright/test'

// Walking-skeleton proof: the SPA boots, calls the API through the Vite proxy,
// and renders what the backend returned. This is the whole stack in one line.
test('the app renders backend health status fetched from the API', async ({ page }) => {
  await page.goto('/')
  await expect(
    page.getByRole('heading', { name: /postgraduate registration system/i }),
  ).toBeVisible()
  await expect(page.getByText(/API status:\s*ok/i)).toBeVisible()
})
