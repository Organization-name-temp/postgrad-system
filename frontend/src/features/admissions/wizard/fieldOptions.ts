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
