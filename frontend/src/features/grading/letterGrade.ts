/**
 * The client-side mirror of the server's grading scale (ADR-0014, ADR-0021). This is a
 * deliberate, duplicated copy of the authoritative PHP `LetterGrade` enum, for UX only — so
 * the Instructor sees the letter the moment they type a mark and can spot a mistyped figure
 * before anything is committed. The server stays the single authority: it re-derives the
 * letter on every save and freezes it onto the Grade, so if the two ever drift the failure
 * mode is a redundant 422 or a corrected letter coming back from the save, never a wrong
 * grade in the record.
 *
 * Kept pure (no React) so the whole mirror is unit-testable, exactly like its backend
 * counterpart. Any change here must be made in `modules/Grading/Internal/Enums/LetterGrade.php`
 * too — and that file, not this one, is the scale.
 */

/** The lowest mark a mark of 0–100 may carry, and the highest. */
export const MIN_MARK = 0
export const MAX_MARK = 100

export interface LetterGradeBand {
  letter: string
  /** The GPA points the letter is worth. */
  points: number
  /** The lowest mark that earns this letter, inclusive. */
  minimum: number
}

/**
 * The nine bands of the ADR-0021 scale, highest first — uniform five-point bands, total over
 * 0–100. (SRS Appendix A's bands are not contiguous and cannot be implemented; the ADR records
 * that replacement, including that it moves the minimum pass from 77% to 75%.)
 */
export const LETTER_GRADES: readonly LetterGradeBand[] = [
  { letter: 'A+', points: 4.0, minimum: 95 },
  { letter: 'A', points: 4.0, minimum: 90 },
  { letter: 'B+', points: 3.3, minimum: 85 },
  { letter: 'B', points: 3.0, minimum: 80 },
  { letter: 'C+', points: 2.5, minimum: 75 },
  { letter: 'C', points: 2.0, minimum: 70 },
  { letter: 'D+', points: 1.3, minimum: 65 },
  { letter: 'D', points: 1.0, minimum: 60 },
  { letter: 'F', points: 0.0, minimum: 0 },
] as const

/**
 * The band a mark falls in, or `null` when the mark is not one the server would accept — out
 * of 0–100, not a number, or carrying more than two decimals. Returning `null` rather than
 * throwing is what lets the editor show "—" while a field is mid-typing or invalid, instead of
 * guessing at a letter.
 */
export function letterGradeFor(mark: number | null | undefined): LetterGradeBand | null {
  if (mark === null || mark === undefined || !Number.isFinite(mark)) return null
  if (mark < MIN_MARK || mark > MAX_MARK) return null

  return LETTER_GRADES.find((band) => mark >= band.minimum) ?? null
}

/**
 * Parse what the Instructor typed into a mark, or `null` when it is not one yet. An empty
 * field is `null` (a blank line, not a zero) and so is anything the server's
 * `numeric|decimal:0,2|between:0,100` rules would reject — the mirror of that rule set, so the
 * live preview and the eventual 422 agree about what counts as a mark.
 */
export function parseMark(input: string): number | null {
  const trimmed = input.trim()
  if (trimmed === '') return null
  // Whole, or up to two decimals — with or without a leading digit, because PHP's `numeric`
  // accepts ".5" and a mirror must never be *stricter* than the rule it mirrors (ADR-0014:
  // the only acceptable drift is a redundant 422, never a valid value the client blocks).
  if (!/^(?:\d+(?:\.\d{1,2})?|\.\d{1,2})$/.test(trimmed)) return null

  const value = Number(trimmed)

  return value >= MIN_MARK && value <= MAX_MARK ? value : null
}

/**
 * Whether what the Instructor typed is a value the server would reject — used to mark the
 * field in error while they are still on it. A blank field is not an error: it is an
 * ungraded line, which is a legitimate state until the Sheet is signed (ticket 03).
 */
export function isInvalidMarkInput(input: string): boolean {
  return input.trim() !== '' && parseMark(input) === null
}
