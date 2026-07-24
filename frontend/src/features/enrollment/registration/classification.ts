import type { ListTheActingStudentsEligibleCoursesForTheOpenSemester200DataCoursesItem } from '../../../shared/api/generated/endpoints'

/** One eligible line as returned by the eligible-courses endpoint (ticket 04). */
export type EligibleCourse =
  ListTheActingStudentsEligibleCoursesForTheOpenSemester200DataCoursesItem

/**
 * The five Enrollment Classifications, in the order the registration page presents
 * them — the student's Program shape from foundation to research (BR-14…26). Mirrors
 * the backend `EnrollmentClassification` enum values; the server tags each eligible
 * line with one of these (display-only grouping — the API stays the authority).
 */
export const CLASSIFICATION_ORDER = [
  'Preparatory',
  'Core',
  'InternalElective',
  'ExternalElective',
  'ResearchCourse',
] as const

export type Classification = (typeof CLASSIFICATION_ORDER)[number]

export interface ClassificationGroup {
  classification: Classification
  courses: EligibleCourse[]
}

/**
 * Group eligible courses by their classification into the fixed presentation order,
 * dropping any classification with no courses. Kept pure (no React) so the grouping is
 * unit-testable on its own — an unknown classification the server might add is simply
 * skipped rather than rendered under no heading.
 */
export function groupByClassification(courses: EligibleCourse[]): ClassificationGroup[] {
  return CLASSIFICATION_ORDER.map((classification) => ({
    classification,
    courses: courses.filter((course) => course.classification === classification),
  })).filter((group) => group.courses.length > 0)
}
