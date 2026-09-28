import type { Page } from '@playwright/test'

/** Wait for finite presentation transitions without blocking on decorative loops. */
export async function settledPresentation(page: Page) {
  await page.evaluate(async () => {
    const animations = document
      .getAnimations()
      .filter((animation) => Number.isFinite(animation.effect?.getComputedTiming().endTime))
    await Promise.all(animations.map((animation) => animation.finished.catch(() => undefined)))
  })
}
