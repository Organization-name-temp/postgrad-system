/**
 * The Step-1 dropdown option values, mirrored from the backend Admissions enums
 * (Modules\Admissions\Internal\Enums — Internal, so they have no generated TS
 * counterpart to import). The server re-validates every value against the enum,
 * so these drive the pickers only. Labels come from i18n where a translation adds
 * value; the enum values themselves are already human-readable, so the test-type
 * and scale options are shown verbatim.
 */
export const GENDERS = ['Male', 'Female'] as const

/**
 * Identification Type values, mirroring the backend `IdentificationType` enum
 * (national_id = exactly 14 digits, passport = 6–9 alphanumeric). Drives which
 * format the identifier field is validated against (ticket 06); the server
 * re-validates. Labels are localized (personalInfo.idTypes.*).
 */
export const ID_TYPES = ['national_id', 'passport'] as const

/** The default/most common identification type — an Egyptian National ID. */
export const ID_TYPE_NATIONAL = 'national_id'

export const PREVIOUS_DEGREES = ['BSc', 'MSc', 'PhD'] as const

export const APPLYING_DEGREES = ['MSc', 'PhD'] as const

export const GPA_SCALES = ['4.0', '4.3', '5.0', '6.0', '7.0', '10.0', '100'] as const

/**
 * The ceiling a GPA/CGPA on the chosen scale may reach — the scale value *is* its
 * ceiling by construction (a 4.0-scale GPA is `0 ≤ v ≤ 4.0`, a percentage `≤ 100`),
 * mirroring the server's {@see GpaScale::ceiling}. An unrecognised/empty scale
 * yields `null` (no bound yet — the field stays disabled until a scale is chosen).
 */
export function gpaScaleCeiling(scale: string): number | null {
  if (!GPA_SCALES.includes(scale as (typeof GPA_SCALES)[number])) return null
  return Number(scale)
}

export const ENGLISH_TESTS = [
  'IELTS',
  'TOEFL iBT',
  'TOEFL PBT',
  'PTE',
  'Cambridge C1',
  'Cambridge C2',
  'Cambridge B2',
  'Duolingo',
  'None',
] as const

/** The one English test that carries no score — mirrors EnglishTest::None. */
export const ENGLISH_TEST_NONE = 'None'

/**
 * The official score range `[min, max]` a reported score must fall within, per test —
 * the client mirror of the server's {@see EnglishTest::scoreRange} (ADR-0014). `None`
 * carries no score and is absent from the map. Consumed by the Zod schema's cross-field
 * check and the step's proactive range hint, so both read one table.
 */
export const ENGLISH_SCORE_RANGES: Record<string, readonly [number, number]> = {
  IELTS: [0, 9],
  'TOEFL iBT': [0, 120],
  'TOEFL PBT': [310, 677],
  PTE: [10, 90],
  'Cambridge C1': [80, 230],
  'Cambridge C2': [80, 230],
  'Cambridge B2': [80, 230],
  Duolingo: [10, 160],
}
