import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

// The Phase 0.5 headline verification: the dev-only /style gallery renders the
// whole design system across all four combos (light/dark × EN/AR). Assertions are
// functional (attribute flips, computed token colour, a11y) — never pixel diffs;
// screenshots are attached as artifacts for human review, not asserted.

const COMBOS = [
  { scheme: 'light', lang: 'en', dir: 'ltr' },
  { scheme: 'dark', lang: 'en', dir: 'ltr' },
  { scheme: 'light', lang: 'ar', dir: 'rtl' },
  { scheme: 'dark', lang: 'ar', dir: 'rtl' },
] as const

test.describe('design-system gallery (/style)', () => {
  test.beforeEach(async ({ page }) => {
    // /style is public + dev-only; stub the auth probe (all methods) so the gallery
    // is offline and there's no expected-401 resource noise in the console.
    await page.route('**/api/v1/me', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: { id: 0, name: 'Preview', email: 'preview@ejust.test', roles: [], permissions: [] },
        }),
      }),
    )
  })

  for (const { scheme, lang, dir } of COMBOS) {
    test(`renders and passes axe in ${scheme} / ${lang}`, async ({ page }, testInfo) => {
      const consoleErrors: string[] = []
      page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text())
      })

      // Pre-set the colour scheme (the pre-paint script reads it), then switch lang.
      await page.addInitScript((s) => localStorage.setItem('mui-mode', s), scheme)
      await page.goto('/style')
      await page.getByRole('button', { name: lang === 'ar' ? 'العربية' : 'English' }).click()

      // The gallery rendered.
      await expect(page.getByTestId('style-gallery')).toBeVisible()

      // The two axes are wired.
      await expect(page.locator('html')).toHaveAttribute('data-mui-color-scheme', scheme)
      await expect(page.locator('html')).toHaveAttribute('dir', dir)
      await expect(page.locator('html')).toHaveAttribute('lang', lang)

      // A token drives real CSS: the brand-red primary variable is emitted.
      const primary = await page.evaluate(() =>
        getComputedStyle(document.documentElement)
          .getPropertyValue('--mui-palette-primary-main')
          .trim(),
      )
      expect(primary.toLowerCase()).toBe('#e4002b')

      // The StatusChip catalogue rendered — many tones, and all three variants.
      expect(await page.locator('[data-tone]').count()).toBeGreaterThan(5)
      for (const v of ['filled', 'filled-soft', 'outlined']) {
        expect(await page.locator(`[data-variant="${v}"]`).count(), v).toBeGreaterThan(0)
      }

      // Shared chart graphics and their exact table series across every theme/direction.
      for (const kind of ['bar', 'line', 'pie']) {
        await expect(page.getByRole('img', { name: `${kind} chart`, exact: true })).toBeVisible()
      }
      for (const kind of ['bar', 'line', 'pie']) {
        await page
          .getByRole('region', { name: `${kind} chart`, exact: true })
          .getByRole('button', { name: lang === 'ar' ? 'عرض كجدول' : 'View as table', exact: true })
          .click()
        const table = page.getByRole('table', { name: `${kind} chart`, exact: true })
        await expect(table.getByRole('row')).toHaveCount(4)
        await expect(table.getByRole('cell', { name: '12', exact: true })).toBeVisible()
        await expect(table.getByRole('cell', { name: '8', exact: true })).toBeVisible()
        await expect(table.getByRole('cell', { name: '5', exact: true })).toBeVisible()
      }

      // Accessibility: no serious/critical violations in this combo (this is where
      // StatusChip filled-soft contrast is exercised in dark mode).
      const results = await new AxeBuilder({ page }).analyze()
      const blocking = results.violations.filter(
        (v) => v.impact === 'serious' || v.impact === 'critical',
      )
      expect(
        blocking,
        JSON.stringify(
          blocking.map((v) => ({ id: v.id, help: v.help, nodes: v.nodes.length })),
          null,
          2,
        ),
      ).toEqual([])

      // Per-combo screenshot artifact — for human review, NOT pixel-asserted.
      await testInfo.attach(`style-${scheme}-${lang}`, {
        body: await page.screenshot({ fullPage: true }),
        contentType: 'image/png',
      })

      expect(consoleErrors, consoleErrors.join('\n')).toEqual([])
    })
  }
})
