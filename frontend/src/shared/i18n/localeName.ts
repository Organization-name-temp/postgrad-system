/**
 * Picks a bilingual entity's name in the active locale, falling back when the
 * chosen language's name is absent. Every bilingual record in the system carries
 * paired name_en/name_ar (catalog entities, admission cycles, …), so the helper
 * lives in `shared` and is consumed by every feature (a feature may not reach
 * into another feature's copy — eslint-plugin-boundaries).
 */
export function pickLocaleName(
  entity: { name_en?: string; name_ar?: string },
  lang: string,
  fallback = '',
): string {
  return (lang === 'ar' ? entity.name_ar : entity.name_en) ?? fallback
}
