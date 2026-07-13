/**
 * The three Course Categories a Curriculum Entry can assign, mirrored from the
 * backend CourseCategory enum (Modules\Catalog\Internal\Enums\CourseCategory) and
 * listed in the order the Program edit page shows them. Category lives on the
 * Curriculum Entry, never on the Course (ADR-0010); the API re-validates it on
 * every mutation and remains the sole authority.
 */
export const COURSE_CATEGORIES = ['Preparatory', 'ProgramCore', 'ResearchElective'] as const

export type CourseCategory = (typeof COURSE_CATEGORIES)[number]
