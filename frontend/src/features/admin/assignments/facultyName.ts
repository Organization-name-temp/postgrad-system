import { pickLocaleName } from '../../../shared/i18n/localeName'

interface FacultyLike {
  id?: number
  name_en?: string
  name_ar?: string
}

/**
 * Resolve an opaque `faculty_id` to its bilingual name by joining against the Catalog
 * faculties list the admin screen already reads. Faculties are opaque to Identity (no
 * FK, ADR-0012), so an assignment row carries only the id; this is where the screen
 * puts a human name to it. A row whose faculty is missing from the list (a Faculty
 * that has since been removed, or not yet loaded) falls back to the given label, so
 * the assignment is still shown and can be unassigned.
 */
export function facultyName(
  faculties: FacultyLike[],
  facultyId: number | undefined,
  lang: string,
  fallback: string,
): string {
  const match = faculties.find((faculty) => faculty.id === facultyId)
  return match ? pickLocaleName(match, lang, fallback) : fallback
}
