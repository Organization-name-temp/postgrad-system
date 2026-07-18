import { z } from 'zod'
import { isValidPhoneNumber } from 'libphonenumber-js'
import {
  APPLYING_DEGREES,
  ENGLISH_SCORE_RANGES,
  ENGLISH_TESTS,
  ENGLISH_TEST_NONE,
  GENDERS,
  GPA_SCALES,
  gpaScaleCeiling,
  ID_TYPES,
  PREVIOUS_DEGREES,
} from './fieldOptions'
import { isCountryCode } from './countries'

/**
 * A minimal translator — the admissions-scoped `t` from react-i18next. The schema
 * takes it so every message is localized in the applicant's language, the same way
 * the authoritative server `422`s are (ADR-0014). Typed structurally (not against
 * i18next's `TFunction`) so the schema stays decoupled from the i18n wiring.
 */
export type Translator = (key: string, options?: Record<string, unknown>) => string

/**
 * The wizard's personal-info form values. Every field is a string — text inputs and
 * dropdowns alike, GPA/score included (a `<TextField type="number">` still yields a
 * string) — except the two boolean flags. The shape mirrors the Step-1 snapshot;
 * the step coerces GPA/score to numbers only when building the API body.
 */
export interface PersonalInfoValues {
  name_en: string
  name_ar: string
  id_type: string
  national_id: string
  phone: string
  date_of_birth: string
  nationality: string
  gender: string
  address_country: string
  address_city: string
  address_street: string
  previous_university: string
  graduation_certificate_name: string
  graduation_date: string
  previous_degree: string
  previous_major: string
  graduation_gpa: string
  graduation_cgpa: string
  gpa_scale: string
  is_ejust_graduate: boolean
  applying_for_degree: string
  english_test: string
  english_score: string
  native_arabic_speaker: boolean
}

/**
 * The identifier format per Identification Type — its pattern and the message key to
 * show when it fails — mirroring the server `IdentificationType`. Kept in one place so
 * the schema's cross-field check and the step's proactive hint share a single mapping
 * (no scattered `id_type` switch).
 */
export const ID_FORMATS: Record<string, { pattern: RegExp; messageKey: string }> = {
  national_id: { pattern: /^\d{14}$/, messageKey: 'validation.nationalId' },
  passport: { pattern: /^[A-Za-z0-9]{6,9}$/, messageKey: 'validation.passport' },
}

/** Midnight today, for the two "not in the future" date checks. */
function startOfToday(): number {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return today.getTime()
}

/**
 * The Zod schema for the personal-info step, built with a live translator so its
 * messages are localized. It is a deliberate, hand-authored **mirror** of the
 * server's `snapshotRules()` — the authoritative gate — kept in lockstep with it
 * (ADR-0014). Ticket 05 mirrors the *current* rules verbatim (free-text identifier,
 * phone, and nationality; blanket GPA/score ranges); tickets 06–07 tighten both
 * sides together.
 */
export function buildPersonalInfoSchema(t: Translator) {
  const required = { error: t('validation.required') }

  /** A required, trimmed text field bounded to `max` (and optionally a `min` length).
   *  Empty always reads as "required"; a too-short value reads as the min-length
   *  message — mirroring the server's `required` → `min` rule ordering. */
  const text = (max: number, min = 1) => {
    let schema = z.string().trim().min(1, required)
    if (min > 1) schema = schema.min(min, { error: t('validation.min', { count: min }) })
    return schema.max(max, { error: t('validation.max', { count: max }) })
  }

  /** A required dropdown constrained to a closed option list (empty ⇒ "choose one"). */
  const choice = <T extends readonly [string, ...string[]]>(values: T) =>
    z.enum(values, { error: t('validation.select') })

  /** A required, non-negative numeric text field. The upper bound is applied
   *  cross-field (GPA vs its chosen scale) in `superRefine`, not fixed here. */
  const number = () =>
    z
      .string()
      .trim()
      .min(1, required)
      .refine((v) => v !== '' && !Number.isNaN(Number(v)), { error: t('validation.number') })
      .refine((v) => Number(v) >= 0, { error: t('validation.nonNegative') })

  /** A required date that must not be in the future — strictly past when `allowToday` is false. */
  const pastDate = (allowToday: boolean) =>
    z
      .string()
      .trim()
      .min(1, required)
      .refine((v) => !Number.isNaN(Date.parse(v)), { error: t('validation.date') })
      .refine(
        (v) => {
          const day = new Date(v)
          day.setHours(0, 0, 0, 0)
          const today = startOfToday()
          return allowToday ? day.getTime() <= today : day.getTime() < today
        },
        { error: t(allowToday ? 'validation.dateNotFuture' : 'validation.datePast') },
      )

  return z
    .object({
      name_en: text(100, 2),
      name_ar: text(100, 2),
      id_type: choice(ID_TYPES),
      // Presence here; the format is cross-field against id_type (refined below).
      national_id: z.string().trim().min(1, required),
      // Stored E.164; must be a valid number (refine skips the empty case so the
      // "required" message wins there, not "invalid phone").
      phone: z
        .string()
        .trim()
        .min(1, required)
        .refine((v) => v === '' || isValidPhoneNumber(v), { error: t('validation.phone') }),
      date_of_birth: pastDate(false),
      // An ISO 3166-1 alpha-2 code chosen from the list.
      nationality: z
        .string()
        .trim()
        .min(1, required)
        .refine((v) => v === '' || isCountryCode(v), { error: t('validation.nationality') }),
      gender: choice(GENDERS),
      address_country: text(100),
      address_city: text(100),
      address_street: text(255),
      previous_university: text(255),
      graduation_certificate_name: text(255),
      graduation_date: pastDate(true),
      previous_degree: choice(PREVIOUS_DEGREES),
      previous_major: text(200),
      graduation_gpa: number(),
      graduation_cgpa: number(),
      gpa_scale: choice(GPA_SCALES),
      is_ejust_graduate: z.boolean(),
      applying_for_degree: choice(APPLYING_DEGREES),
      english_test: choice(ENGLISH_TESTS),
      // Presence is cross-field (required unless the test is None); refined below.
      english_score: z.string(),
      native_arabic_speaker: z.boolean(),
    })
    .superRefine((values, ctx) => {
      // The identifier must match the chosen Identification Type's format — 14 digits
      // for a National ID, 6–9 alphanumeric for a Passport (mirrors the server).
      const id = values.national_id.trim()
      const format = ID_FORMATS[values.id_type]
      if (id !== '' && format && !format.pattern.test(id)) {
        ctx.addIssue({ code: 'custom', path: ['national_id'], message: t(format.messageKey) })
      }

      // GPA/CGPA are bounded by the chosen scale's ceiling (a 90 on a 4.0 scale is
      // invalid) — the cross-field mirror of the server's rule. Runs only once a scale
      // is chosen; the fields stay disabled until then, so there is nothing to check.
      const ceiling = gpaScaleCeiling(values.gpa_scale)
      if (ceiling !== null) {
        for (const field of ['graduation_gpa', 'graduation_cgpa'] as const) {
          const raw = values[field].trim()
          if (raw !== '' && !Number.isNaN(Number(raw)) && Number(raw) > ceiling) {
            ctx.addIssue({
              code: 'custom',
              path: [field],
              message: t('validation.range', { min: 0, max: ceiling }),
            })
          }
        }
      }

      // English score mirrors the server's `required_unless:english_test,None` plus the
      // per-test range: required for any real test and bounded by that test's official
      // range ({@see ENGLISH_SCORE_RANGES}); ignored for None.
      const raw = values.english_score.trim()
      const needsScore = values.english_test !== ENGLISH_TEST_NONE
      if (needsScore && raw === '') {
        ctx.addIssue({
          code: 'custom',
          path: ['english_score'],
          message: t('validation.scoreRequired'),
        })
        return
      }
      const range = ENGLISH_SCORE_RANGES[values.english_test]
      if (raw !== '' && range) {
        const score = Number(raw)
        if (Number.isNaN(score) || score < range[0] || score > range[1]) {
          ctx.addIssue({
            code: 'custom',
            path: ['english_score'],
            message: t('validation.range', { min: range[0], max: range[1] }),
          })
        }
      }
    })
}

/** The resolved schema type (used to type the RHF resolver and form). */
export type PersonalInfoSchema = ReturnType<typeof buildPersonalInfoSchema>
