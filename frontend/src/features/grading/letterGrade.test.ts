import { describe, expect, it } from 'vitest'
import { isInvalidMarkInput, letterGradeFor, LETTER_GRADES, parseMark } from './letterGrade'

/*
| The client mirror of the grading scale (ADR-0014, ADR-0021). It exists only so the
| Instructor sees a letter the moment they type a mark; the server re-derives and freezes the
| real one. These assertions are therefore also the **drift check**: they are the same
| boundaries LetterGradeTest.php asserts on the PHP side, so if one table moves without the
| other, one of the two suites fails.
*/

describe('letterGradeFor', () => {
  it('mirrors the nine letters and their GPA points', () => {
    expect(LETTER_GRADES.map((b) => [b.letter, b.points])).toEqual([
      ['A+', 4.0],
      ['A', 4.0],
      ['B+', 3.3],
      ['B', 3.0],
      ['C+', 2.5],
      ['C', 2.0],
      ['D+', 1.3],
      ['D', 1.0],
      ['F', 0.0],
    ])
  })

  it.each([
    [59.99, 'F'],
    [60, 'D'],
    [64.99, 'D'],
    [65, 'D+'],
    [69.99, 'D+'],
    [70, 'C'],
    [74.99, 'C'],
    [75, 'C+'],
    [79.99, 'C+'],
    [80, 'B'],
    [84.99, 'B'],
    [85, 'B+'],
    [89.99, 'B+'],
    [90, 'A'],
    [94.99, 'A'],
    [95, 'A+'],
  ])('maps %d to %s — both sides of every band boundary', (mark, letter) => {
    expect(letterGradeFor(mark)?.letter).toBe(letter)
  })

  it('maps the two extremes of the range', () => {
    expect(letterGradeFor(0)?.letter).toBe('F')
    expect(letterGradeFor(100)?.letter).toBe('A+')
  })

  it('has no holes — the three marks Appendix A could not represent all map', () => {
    expect(letterGradeFor(91)?.letter).toBe('A')
    expect(letterGradeFor(81)?.letter).toBe('B')
    expect(letterGradeFor(71)?.letter).toBe('C')
  })

  it('carries the points alongside the letter', () => {
    expect(letterGradeFor(82.5)).toEqual({ letter: 'B', points: 3.0, minimum: 80 })
  })

  it('has no letter for a mark outside 0–100 or for nothing at all', () => {
    expect(letterGradeFor(-0.01)).toBeNull()
    expect(letterGradeFor(100.01)).toBeNull()
    expect(letterGradeFor(null)).toBeNull()
    expect(letterGradeFor(undefined)).toBeNull()
    expect(letterGradeFor(Number.NaN)).toBeNull()
  })
})

describe('parseMark', () => {
  it('reads a whole number and a two-decimal figure', () => {
    expect(parseMark('82')).toBe(82)
    expect(parseMark('82.5')).toBe(82.5)
    expect(parseMark('82.55')).toBe(82.55)
    expect(parseMark(' 100 ')).toBe(100)
  })

  it('accepts a bare-decimal figure, which the server also accepts', () => {
    // PHP's `numeric` reads ".5" as 0.5, so the mirror must too — a mirror that is stricter
    // than the rule blocks a value the server would have taken (ADR-0014).
    expect(parseMark('.5')).toBe(0.5)
    expect(parseMark('.75')).toBe(0.75)
    expect(parseMark('.')).toBeNull()
  })

  it('treats an empty field as no mark, not a zero', () => {
    expect(parseMark('')).toBeNull()
    expect(parseMark('   ')).toBeNull()
  })

  it('rejects what the server would reject — out of range, too precise, not a number', () => {
    expect(parseMark('101')).toBeNull()
    expect(parseMark('-1')).toBeNull()
    expect(parseMark('82.555')).toBeNull()
    expect(parseMark('ninety')).toBeNull()
    expect(parseMark('8 2')).toBeNull()
  })
})

describe('isInvalidMarkInput', () => {
  it('flags a value the server would reject', () => {
    expect(isInvalidMarkInput('101')).toBe(true)
    expect(isInvalidMarkInput('82.555')).toBe(true)
  })

  it('does not flag a blank field — an ungraded line is a legitimate state', () => {
    expect(isInvalidMarkInput('')).toBe(false)
  })

  it('does not flag a valid mark', () => {
    expect(isInvalidMarkInput('0')).toBe(false)
    expect(isInvalidMarkInput('82.5')).toBe(false)
    expect(isInvalidMarkInput('100')).toBe(false)
  })
})
