/**
 * The fixed Research Types a Course can carry, mirrored from the backend
 * ResearchType enum (Modules\Catalog\Internal\Enums\ResearchType). Used to populate
 * the Research Type picker; the API re-validates the value on every mutation and
 * remains the sole authority. The value is optional — a Course may carry a Research
 * Type or none (the picker's "none" option sends no value). Labels are translated
 * (catalog.courses.researchTypes.*); the stored value is the code itself (BR-25).
 */
export const RESEARCH_TYPES = ['PBL', 'Seminar'] as const

export type ResearchType = (typeof RESEARCH_TYPES)[number]
