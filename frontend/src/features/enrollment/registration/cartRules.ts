import { z } from 'zod'
import type { EligibleCourse } from './classification'

/**
 * The client-side mirror of the server's cart rules (ADR-0014). This is a deliberate,
 * duplicated copy of the authoritative PHP `CartValidator` / `CreditHoursValidator`,
 * for UX only — the server stays the single gate, and if the two ever drift the failure
 * mode is a redundant 422, never accepting bad data. Kept pure (no React) so the whole
 * mirror is unit-testable, exactly like its backend counterpart.
 */

/** The credit-hour window, mirroring `config/enrollment.php`'s defaults (6 / 12). */
export const MIN_CREDIT_HOURS = 6
export const MAX_CREDIT_HOURS = 12

export type CartMode = 'Regular' | 'Audit'

/** One line the student is building — an offering plus how they mean to take it. */
export interface CartLineValue {
  offering_id: number
  mode: CartMode
}

/** The eligible offerings a cart draws from, indexed by offering id. */
export type OfferingIndex = Map<number, EligibleCourse>

/** A single reason the cart cannot be submitted, mirroring `CartViolation`'s codes. */
export type CartIssueCode =
  'belowMin' | 'aboveMax' | 'externalCap' | 'researchCap' | 'duplicate' | 'clash' | 'full'

export interface CartIssue {
  code: CartIssueCode
  /** The offerings the issue is about — used to highlight the offending lines. */
  offeringIds: number[]
  /** The cart's total credit hours (only set on the credit-hour issues). */
  total?: number
}

export interface CartContext {
  /** Whether the target semester is the student's first — waives the 6-CH floor. */
  firstSemester: boolean
}

/** Index the eligible list by offering id, dropping any line missing an id. */
export function indexOfferings(courses: EligibleCourse[]): OfferingIndex {
  const entries = courses
    .filter(
      (course): course is EligibleCourse & { offering_id: number } => course.offering_id != null,
    )
    .map((course) => [course.offering_id, course] as const)

  return new Map(entries)
}

/** The cart's total credit hours — every Regular and Audit line loads its course's CH. */
export function creditHourTotal(lines: CartLineValue[], offerings: OfferingIndex): number {
  return lines.reduce((sum, line) => sum + (offerings.get(line.offering_id)?.total_ch ?? 0), 0)
}

/** Parse a "HH:MM" clock to minutes from midnight (seconds, if present, are ignored). */
function toMinutes(clock: string): number {
  const [hours, minutes] = clock.split(':').map((part) => Number.parseInt(part, 10))
  return hours * 60 + minutes
}

type Meeting = NonNullable<EligibleCourse['meetings']>[number]

/** Whether two weekly meetings clash — same weekday, overlapping half-open intervals. */
function meetingsClash(a: Meeting, b: Meeting): boolean {
  if (
    a.weekday !== b.weekday ||
    a.starts_at == null ||
    a.ends_at == null ||
    b.starts_at == null ||
    b.ends_at == null
  ) {
    return false
  }
  return (
    toMinutes(a.starts_at) < toMinutes(b.ends_at) && toMinutes(b.starts_at) < toMinutes(a.ends_at)
  )
}

/** Whether any meeting of one offering overlaps any meeting of the other. */
function offeringsClash(a: EligibleCourse, b: EligibleCourse): boolean {
  const meetingsA = a.meetings ?? []
  const meetingsB = b.meetings ?? []
  return meetingsA.some((slotA) => meetingsB.some((slotB) => meetingsClash(slotA, slotB)))
}

/**
 * Evaluate a cart against the mirror rules and return every issue (empty ⇒ submittable) —
 * the same rule set the backend `CartValidator` enforces: the credit-hour window (floor
 * waivable in the first semester, ceiling always), at most one External Elective and one
 * Research Course, no duplicate course, no time clash, and no full offering.
 */
export function evaluateCart(
  lines: CartLineValue[],
  offerings: OfferingIndex,
  context: CartContext,
): CartIssue[] {
  const issues: CartIssue[] = []
  const allIds = lines.map((line) => line.offering_id)

  // Credit-hour load.
  const total = creditHourTotal(lines, offerings)
  if (!context.firstSemester && total < MIN_CREDIT_HOURS) {
    issues.push({ code: 'belowMin', offeringIds: allIds, total })
  }
  if (total > MAX_CREDIT_HOURS) {
    issues.push({ code: 'aboveMax', offeringIds: allIds, total })
  }

  // Composition caps.
  const withClassification = (classification: string): number[] =>
    lines
      .filter((line) => offerings.get(line.offering_id)?.classification === classification)
      .map((line) => line.offering_id)

  const externals = withClassification('ExternalElective')
  if (externals.length > 1) {
    issues.push({ code: 'externalCap', offeringIds: externals })
  }
  const research = withClassification('ResearchCourse')
  if (research.length > 1) {
    issues.push({ code: 'researchCap', offeringIds: research })
  }

  // No duplicate course (incl. Regular + Audit of the same course).
  const byCourse = new Map<number, number[]>()
  for (const line of lines) {
    const courseId = offerings.get(line.offering_id)?.course?.id
    if (courseId == null) continue
    byCourse.set(courseId, [...(byCourse.get(courseId) ?? []), line.offering_id])
  }
  for (const offeringIds of byCourse.values()) {
    if (offeringIds.length > 1) {
      issues.push({ code: 'duplicate', offeringIds })
    }
  }

  // Time clashes (pairwise across chosen offerings).
  for (let i = 0; i < lines.length; i++) {
    for (let j = i + 1; j < lines.length; j++) {
      const a = offerings.get(lines[i].offering_id)
      const b = offerings.get(lines[j].offering_id)
      if (a && b && offeringsClash(a, b)) {
        issues.push({ code: 'clash', offeringIds: [lines[i].offering_id, lines[j].offering_id] })
      }
    }
  }

  // Seat capacity — a capped offering must have a seat left.
  for (const line of lines) {
    const offering = offerings.get(line.offering_id)
    if (offering && offering.seats_remaining != null && offering.seats_remaining < 1) {
      issues.push({ code: 'full', offeringIds: [line.offering_id] })
    }
  }

  return issues
}

/** The RHF form shape — a field array of cart lines (ADR-0014, the PersonalInfoStep pattern). */
export interface CartFormValues {
  lines: CartLineValue[]
}

/**
 * Build the Zod schema RHF validates the cart against. It closes over the eligible
 * offerings and the first-semester context, and defers the cross-line rules to the shared
 * {@link evaluateCart} so the schema and the live summary can never disagree. The schema's
 * job is to gate submit (`formState.isValid`); the component renders the localized issue
 * messages from {@link evaluateCart} directly.
 */
export function buildCartSchema(offerings: OfferingIndex, context: CartContext) {
  return z
    .object({
      lines: z
        .array(
          z.object({
            offering_id: z.number(),
            mode: z.enum(['Regular', 'Audit']),
          }),
        )
        .min(1),
    })
    .superRefine((value, ctx) => {
      for (const issue of evaluateCart(value.lines, offerings, context)) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['lines'], message: issue.code })
      }
    })
}
