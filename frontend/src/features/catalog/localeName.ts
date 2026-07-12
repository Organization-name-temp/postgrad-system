/**
 * Picks a catalog entity's name in the active locale, falling back when the
 * chosen language's name is absent. Every catalog entity (Faculty, Department,
 * Program, Course) carries paired name_en/name_ar, so this lives at the feature
 * root beside the status→chip map rather than being repeated per component.
 */
export function pickLocaleName(
  entity: { name_en?: string; name_ar?: string },
  lang: string,
  fallback = '',
): string {
  return (lang === 'ar' ? entity.name_ar : entity.name_en) ?? fallback
}
