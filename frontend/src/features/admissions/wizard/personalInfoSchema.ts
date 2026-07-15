import { z } from 'zod'
import {
  APPLYING_DEGREES,
  ENGLISH_TESTS,
  ENGLISH_TEST_NONE,
  GENDERS,
  GPA_SCALES,
  PREVIOUS_DEGREES,
} from './fieldOptions'

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

/** The server's `english_score` ceiling — a blanket 0–300 (tightened per test in ticket 07). */
const ENGLISH_SCORE_MAX = 300

/** The server's blanket GPA/CGPA ceiling — 0–100 (tied to the chosen scale in ticket 07). */
const GPA_MAX = 100

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

  /** A required numeric text field bounded 0 ≤ v ≤ `max`. */
  const number = (max: number) =>
    z
      .string()
      .trim()
      .min(1, required)
      .refine((v) => v !== '' && !Number.isNaN(Number(v)), { error: t('validation.number') })
      .refine((v) => Number(v) >= 0 && Number(v) <= max, {
        error: t('validation.range', { min: 0, max }),
      })

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
      national_id: text(50),
      phone: text(30),
      date_of_birth: pastDate(false),
      nationality: text(100),
      gender: choice(GENDERS),
      address_country: text(100),
      address_city: text(100),
      address_street: text(255),
      previous_university: text(255),
      graduation_certificate_name: text(255),
      graduation_date: pastDate(true),
      previous_degree: choice(PREVIOUS_DEGREES),
      previous_major: text(200),
      graduation_gpa: number(GPA_MAX),
      graduation_cgpa: number(GPA_MAX),
      gpa_scale: choice(GPA_SCALES),
      is_ejust_graduate: z.boolean(),
      applying_for_degree: choice(APPLYING_DEGREES),
      english_test: choice(ENGLISH_TESTS),
      // Presence is cross-field (required unless the test is None); refined below.
      english_score: z.string(),
      native_arabic_speaker: z.boolean(),
    })
    .superRefine((values, ctx) => {
      // English score mirrors the server's `required_unless:english_test,None` plus
      // the blanket 0–300 range: required and bounded for any real test, ignored for None.
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
      if (raw !== '') {
        const score = Number(raw)
        if (Number.isNaN(score) || score < 0 || score > ENGLISH_SCORE_MAX) {
          ctx.addIssue({
            code: 'custom',
            path: ['english_score'],
            message: t('validation.range', { min: 0, max: ENGLISH_SCORE_MAX }),
          })
        }
      }
    })
}

/** The resolved schema type (used to type the RHF resolver and form). */
export type PersonalInfoSchema = ReturnType<typeof buildPersonalInfoSchema>
