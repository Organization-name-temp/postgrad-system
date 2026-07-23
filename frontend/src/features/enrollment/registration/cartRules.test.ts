import { describe, expect, it } from 'vitest'
import type { EligibleCourse } from './classification'
import {
  MAX_CREDIT_HOURS,
  MIN_CREDIT_HOURS,
  creditHourTotal,
  evaluateCart,
  indexOfferings,
  type CartIssueCode,
  type CartLineValue,
} from './cartRules'

/**
 * The client-side cart mirror (ADR-0014), unit-tested directly — the deliberate duplicate
 * of the backend `CartValidator`/`CreditHoursValidator`. Only the mirror is exercised
 * here; the server stays the authority (covered by the Pest feature tests).
 */

let nextId = 0

/** A minimal eligible offering with sensible defaults, overridable per case. */
function offering(overrides: Record<string, unknown> = {}): EligibleCourse {
  nextId += 1
  return {
    offering_id: nextId,
    classification: 'Core',
    capacity: 30,
    seats_remaining: 30,
    total_ch: 3,
    course: {
      id: nextId,
      code: `DEP${500 + nextId}`,
      name_en: 'Course',
      name_ar: 'مقرر',
      level: 500,
    },
    instructor: { id: 1, name: 'Dr. Instructor', email: 'i@ejust.test' },
    meetings: [{ weekday: 1, starts_at: '09:00', ends_at: '11:00' }],
    ...overrides,
  } as EligibleCourse
}

/** Build the offering index and the cart lines for a set of offerings. */
function cart(offerings: EligibleCourse[], modes: Record<number, 'Regular' | 'Audit'> = {}) {
  const index = indexOfferings(offerings)
  const lines: CartLineValue[] = offerings.map((o) => ({
    offering_id: o.offering_id as number,
    mode: modes[o.offering_id as number] ?? 'Regular',
  }))
  return { index, lines }
}

/** The set of issue codes returned for a cart. */
function codes(offerings: EligibleCourse[], firstSemester = false): CartIssueCode[] {
  const { index, lines } = cart(offerings)
  return evaluateCart(lines, index, { firstSemester }).map((issue) => issue.code)
}

describe('creditHourTotal', () => {
  it('sums the credit hours of every line, Regular or Audit', () => {
    const { index, lines } = cart([offering({ total_ch: 3 }), offering({ total_ch: 4 })], {})
    expect(creditHourTotal(lines, index)).toBe(7)
  })
})

describe('evaluateCart credit-hour window', () => {
  it('flags a cart below the floor', () => {
    expect(codes([offering({ total_ch: 3 })])).toContain('belowMin')
  })

  it('waives the floor in the first semester', () => {
    expect(codes([offering({ total_ch: 3 })], true)).not.toContain('belowMin')
  })

  it('accepts a cart exactly on the floor', () => {
    expect(codes([offering({ total_ch: MIN_CREDIT_HOURS, meetings: [] })])).not.toContain(
      'belowMin',
    )
  })

  it('flags a cart above the ceiling', () => {
    const many = Array.from({ length: 5 }, (_, i) =>
      offering({ total_ch: 3, meetings: [{ weekday: i, starts_at: '09:00', ends_at: '11:00' }] }),
    )
    const found = codes(many)
    expect(creditHourTotal(cart(many).lines, cart(many).index)).toBeGreaterThan(MAX_CREDIT_HOURS)
    expect(found).toContain('aboveMax')
  })
})

describe('evaluateCart composition and schedule rules', () => {
  it('caps external electives at one', () => {
    const externals = [
      offering({
        classification: 'ExternalElective',
        meetings: [{ weekday: 1, starts_at: '09:00', ends_at: '11:00' }],
      }),
      offering({
        classification: 'ExternalElective',
        meetings: [{ weekday: 2, starts_at: '09:00', ends_at: '11:00' }],
      }),
    ]
    expect(codes(externals)).toContain('externalCap')
  })

  it('caps research courses at one', () => {
    const research = [
      offering({
        classification: 'ResearchCourse',
        meetings: [{ weekday: 1, starts_at: '09:00', ends_at: '11:00' }],
      }),
      offering({
        classification: 'ResearchCourse',
        meetings: [{ weekday: 2, starts_at: '09:00', ends_at: '11:00' }],
      }),
    ]
    expect(codes(research)).toContain('researchCap')
  })

  it('flags the same course added twice', () => {
    const shared = { id: 99, code: 'DEP600', name_en: 'Shared', name_ar: 'مشترك', level: 500 }
    const dup = [
      offering({
        course: shared,
        meetings: [{ weekday: 1, starts_at: '09:00', ends_at: '11:00' }],
      }),
      offering({
        course: shared,
        meetings: [{ weekday: 2, starts_at: '09:00', ends_at: '11:00' }],
      }),
    ]
    expect(codes(dup)).toContain('duplicate')
  })

  it('flags two offerings that clash and clears ones that merely touch', () => {
    const clashing = [
      offering({
        course: { id: 1, code: 'A', name_en: 'A', name_ar: 'أ', level: 500 },
        meetings: [{ weekday: 1, starts_at: '09:00', ends_at: '11:00' }],
      }),
      offering({
        course: { id: 2, code: 'B', name_en: 'B', name_ar: 'ب', level: 500 },
        meetings: [{ weekday: 1, starts_at: '10:00', ends_at: '12:00' }],
      }),
    ]
    const touching = [
      offering({
        course: { id: 3, code: 'C', name_en: 'C', name_ar: 'ج', level: 500 },
        meetings: [{ weekday: 1, starts_at: '09:00', ends_at: '11:00' }],
      }),
      offering({
        course: { id: 4, code: 'D', name_en: 'D', name_ar: 'د', level: 500 },
        meetings: [{ weekday: 1, starts_at: '11:00', ends_at: '13:00' }],
      }),
    ]
    expect(codes(clashing)).toContain('clash')
    expect(codes(touching)).not.toContain('clash')
  })

  it('flags a full offering', () => {
    expect(codes([offering({ seats_remaining: 0 })])).toContain('full')
    expect(codes([offering({ seats_remaining: null })])).not.toContain('full')
  })

  it('returns no issues for a clean two-course cart', () => {
    const clean = [
      offering({
        course: { id: 1, code: 'A', name_en: 'A', name_ar: 'أ', level: 500 },
        meetings: [{ weekday: 1, starts_at: '09:00', ends_at: '11:00' }],
      }),
      offering({
        course: { id: 2, code: 'B', name_en: 'B', name_ar: 'ب', level: 500 },
        meetings: [{ weekday: 2, starts_at: '09:00', ends_at: '11:00' }],
      }),
    ]
    expect(codes(clean)).toEqual([])
  })
})
