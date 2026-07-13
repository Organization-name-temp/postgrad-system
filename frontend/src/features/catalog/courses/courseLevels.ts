/**
 * The fixed postgraduate levels a Course can sit at, mirrored from the backend
 * CourseLevel enum (Modules\Catalog\Internal\Enums\CourseLevel). Used to populate
 * the level picker; the API re-validates the value on every mutation and remains
 * the sole authority. The digit is the same in every locale, so it needs no
 * translation.
 */
export const COURSE_LEVELS = [400, 500, 600, 700] as const

export type CourseLevel = (typeof COURSE_LEVELS)[number]
