import { pickLocaleName } from '../../../shared/i18n/localeName'

interface DepartmentLike {
  id?: number
  code?: string
  name_en?: string
  name_ar?: string
}

interface AssignmentLike {
  department_id?: number
  university_wide?: boolean
}

export interface UncoveredScope {
  /** Stable React key — the department id, or the university-wide sentinel. */
  key: string
  /** The scope named in the active locale, ready to render. */
  label: string
}

/**
 * The review scopes that currently have **no Department Head** (phase 4, ticket 02):
 * every Catalog Department nobody is assigned to, plus the university-wide Preparatory
 * scope when nobody covers that either.
 *
 * This is the one genuinely derived thing on the screen, and it exists because the gap
 * is otherwise invisible: a Department with no Head has no row in the assignments
 * table, so its absence is exactly what an administrator would have to notice. A
 * signed Grade Sheet for such a Department would strand with nobody to review it.
 *
 * Deactivated Departments are **included**. A retired Department can still hold a
 * semester's ungraded Offerings, and the cost of listing one nobody needs is a line an
 * administrator reads past — while the cost of hiding one is the stranded grades this
 * whole list exists to prevent.
 *
 * The university-wide scope is named through `universityWideLabel` (which the caller
 * localises) rather than rendered as a blank Department, so the list never asks a
 * reader to interpret an empty value.
 */
export function uncoveredScopes(
  departments: DepartmentLike[],
  assignments: AssignmentLike[],
  lang: string,
  universityWideLabel: string,
): UncoveredScope[] {
  const covered = new Set(
    assignments
      .filter((assignment) => !assignment.university_wide)
      .map((assignment) => assignment.department_id),
  )

  const uncovered: UncoveredScope[] = departments
    .filter((department) => !covered.has(department.id))
    .map((department) => ({
      key: `department-${department.id}`,
      label: pickLocaleName(department, lang, department.code ?? ''),
    }))

  if (!assignments.some((assignment) => assignment.university_wide)) {
    uncovered.push({ key: 'scope-university-wide', label: universityWideLabel })
  }

  return uncovered
}
