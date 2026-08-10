import { pickLocaleName } from '../../../shared/i18n/localeName'

interface BilingualEntity {
  id?: number
  name_en?: string
  name_ar?: string
}

/**
 * Resolve an opaque Catalog id to its bilingual name by joining against the Catalog
 * list the admin screen already reads — a Faculty for the Dean assignments (ticket
 * 08), a Department for the Department-Head assignments (phase 4, ticket 02). Catalog
 * entities are opaque to Identity (no FK, ADR-0012), so an assignment row carries only
 * the id; this is where the screen puts a human name to it.
 *
 * A row whose entity is missing from the list (one since removed, or simply not loaded
 * yet) falls back to the given label, so the assignment is still shown and can be
 * unassigned.
 *
 * An absent id also falls back — and never means "university-wide". That scope is a
 * deliberate `null` the caller recognises and names *before* reaching this helper, so
 * a missing entity and a chosen scope can never render alike.
 */
export function nameById(
  entities: BilingualEntity[],
  id: number | undefined,
  lang: string,
  fallback: string,
): string {
  const match = entities.find((entity) => entity.id === id)
  return match ? pickLocaleName(match, lang, fallback) : fallback
}
