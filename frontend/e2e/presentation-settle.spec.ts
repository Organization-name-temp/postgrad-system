import { test, expect } from '@playwright/test'
import { settledPresentation } from './settledPresentation'

test('a theme audit waits for rendered label colour and ignores infinite animation', async ({
  page,
}) => {
  // The gallery and auth probe are offline: this regression owns no seeded account.
  await page.route('**/api/v1/me', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { id: 0, name: 'Preview', email: 'preview@ejust.test', roles: [], permissions: [] },
      }),
    }),
  )
  await page.addInitScript(() => localStorage.setItem('mui-mode', 'dark'))
  await page.goto('/style')
  const label = page.locator('label').filter({ hasText: 'University ID' })
  await expect(label).toBeVisible()
  await label.evaluate((element) => {
    // Stretch the real MUI transition so an immediate scan fails deterministically.
    ;(element as HTMLElement).style.transitionDuration = '1s'
    element.animate([{ opacity: 1 }, { opacity: 1 }], { duration: 1000, iterations: Infinity })
  })
  await page.getByRole('button', { name: 'Toggle light and dark theme' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', 'light')
  const expected = await page.evaluate(() => {
    const probe = document.createElement('span')
    probe.style.color = 'var(--mui-palette-text-secondary)'
    document.body.append(probe)
    const colour = getComputedStyle(probe).color
    probe.remove()
    return colour
  })
  await settledPresentation(page)
  expect(await label.evaluate((element) => getComputedStyle(element).color)).toBe(expected)
  expect(
    await label.evaluate((element) =>
      element
        .getAnimations()
        .some((animation) => animation.effect?.getComputedTiming().endTime === Infinity),
    ),
  ).toBe(true)
})
