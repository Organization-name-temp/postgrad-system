import { describe, expect, it } from 'vitest'
import { uncoveredScopes } from './uncoveredScopes'

const departments = [
  { id: 1, code: 'CE', name_en: 'Computer Engineering', name_ar: 'هندسة الحاسبات' },
  { id: 2, code: 'EE', name_en: 'Electrical Engineering', name_ar: 'الهندسة الكهربائية' },
]

const UNIVERSITY_WIDE = 'University-wide (Preparatory courses)'

const labels = (
  assignments: { department_id?: number; university_wide?: boolean }[],
  lang = 'en',
): string[] =>
  uncoveredScopes(departments, assignments, lang, UNIVERSITY_WIDE).map((scope) => scope.label)

describe('uncoveredScopes', () => {
  it('lists every department and the university-wide scope when nothing is assigned', () => {
    expect(labels([])).toEqual(['Computer Engineering', 'Electrical Engineering', UNIVERSITY_WIDE])
  })

  it('drops a department once it has a Head', () => {
    expect(labels([{ department_id: 1 }])).toEqual(['Electrical Engineering', UNIVERSITY_WIDE])
  })

  it('drops the university-wide scope once it has a Head', () => {
    expect(labels([{ department_id: undefined, university_wide: true }])).toEqual([
      'Computer Engineering',
      'Electrical Engineering',
    ])
  })

  it('reports nothing when every scope is covered', () => {
    expect(labels([{ department_id: 1 }, { department_id: 2 }, { university_wide: true }])).toEqual(
      [],
    )
  })

  it('does not let a university-wide Head cover a department', () => {
    // The two scopes are disjoint server-side, so the warning must not be silenced by
    // an assignment that reviews something else entirely.
    expect(labels([{ university_wide: true }])).toEqual([
      'Computer Engineering',
      'Electrical Engineering',
    ])
  })

  it('does not let a department Head cover the university-wide scope', () => {
    expect(labels([{ department_id: 1 }, { department_id: 2 }])).toEqual([UNIVERSITY_WIDE])
  })

  it('names the uncovered departments in Arabic', () => {
    expect(labels([{ department_id: 1 }, { university_wide: true }], 'ar')).toEqual([
      'الهندسة الكهربائية',
    ])
  })

  it('keys each scope distinctly so the list can render', () => {
    const keys = uncoveredScopes(departments, [], 'en', UNIVERSITY_WIDE).map((scope) => scope.key)
    expect(new Set(keys).size).toBe(keys.length)
  })
})
