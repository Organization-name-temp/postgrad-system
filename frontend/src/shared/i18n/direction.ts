// Pure, side-effect-free locale-direction helpers. Kept separate from `config.ts`
// (which initialises i18next on import) so the theme layer can decide direction
// without triggering that side effect. Single source of truth for "which locales
// are RTL" — consumed by the i18n config, the theme factory, and the provider.

const RTL_LOCALES = new Set<string>(['ar'])

/** True for right-to-left locales (matches on the base language, so `ar-EG` counts). */
export function isRtlLocale(lng: string): boolean {
  return RTL_LOCALES.has(lng) || RTL_LOCALES.has(lng.split('-')[0])
}

export function directionFor(lng: string): 'rtl' | 'ltr' {
  return isRtlLocale(lng) ? 'rtl' : 'ltr'
}
