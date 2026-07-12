// WCAG 2.1 relative-luminance contrast. Pure, dependency-free — used to gate the
// design tokens (and available to any a11y check). Ratios per WCAG 2.x:
// https://www.w3.org/TR/WCAG21/#dfn-contrast-ratio

export const AA_NORMAL = 4.5 // normal text
export const AA_LARGE = 3 // large text (>= 24px, or >= 18.66px bold) and UI components

function parseHex(hex: string): [number, number, number] {
  const h = hex.replace('#', '')
  const full = h.length === 3 ? h.replace(/(.)/g, '$1$1') : h
  // Fail fast: a malformed colour would otherwise yield NaN and a silently wrong
  // ratio — dangerous for a gate.
  if (!/^[0-9a-fA-F]{6}$/.test(full)) {
    throw new Error(`contrast: invalid hex colour "${hex}"`)
  }
  const n = Number.parseInt(full, 16)
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255]
}

function channelLuminance(c: number): number {
  const s = c / 255
  return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4)
}

function relativeLuminance(hex: string): number {
  const [r, g, b] = parseHex(hex)
  return 0.2126 * channelLuminance(r) + 0.7152 * channelLuminance(g) + 0.0722 * channelLuminance(b)
}

/** WCAG contrast ratio in [1, 21]. Order-independent. */
export function contrastRatio(a: string, b: string): number {
  const la = relativeLuminance(a)
  const lb = relativeLuminance(b)
  const [hi, lo] = la >= lb ? [la, lb] : [lb, la]
  return (hi + 0.05) / (lo + 0.05)
}

/** True if `fg` on `bg` meets WCAG AA — 4.5:1 normal, 3:1 large/UI. */
export function meetsAA(fg: string, bg: string, opts: { large?: boolean } = {}): boolean {
  return contrastRatio(fg, bg) >= (opts.large ? AA_LARGE : AA_NORMAL)
}
