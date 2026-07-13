/**
 * The fixed degree levels a Program can offer, mirrored from the backend
 * DegreeLevel enum (Modules\Catalog\Internal\Enums\DegreeLevel). Used to populate
 * the degree-level picker and to translate a Program's level for display; the API
 * re-validates the value on every mutation and remains the sole authority.
 */
export const DEGREE_LEVELS = ['MSc', 'PhD'] as const

export type DegreeLevel = (typeof DEGREE_LEVELS)[number]
