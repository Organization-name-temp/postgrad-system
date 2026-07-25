import type { StatusChipProps, StatusTone, StatusChipVariant } from '../../shared/ui/StatusChip'

/**
 * Maps a Grade Sheet's state, and one line's, onto <StatusChip> props, per design-system.md
 * §10. Colour is never the only signal — the label always accompanies it (WCAG 1.4.1); the
 * caller passes the already-localised label. The map lives at the feature root, the way each
 * feature colocates its own status→chip map (no status names in shared/ui).
 *
 * Only `Draft` is reachable in ticket 01 — nothing is signed or reviewed yet — but all four
 * Sheet states are mapped up front so ticket 03's signature loop renders a stable chip, the
 * same discipline courseStatusChip followed in Phase 3.
 */
type ChipStyle = { tone: StatusTone; variant: StatusChipVariant }

const SHEET_TONE: Record<string, ChipStyle> = {
  // Being filled in — neutral: an unfinished Sheet is the normal working state, not a warning.
  Draft: { tone: 'neutral', variant: 'outlined' },
  // Signed and waiting on the Department Head — informational, and read-only.
  Submitted: { tone: 'info', variant: 'filled-soft' },
  // Sent back with a note: the one Sheet state that needs the Instructor to act.
  Returned: { tone: 'warning', variant: 'filled-soft' },
  // Part of the academic record, and permanently closed.
  Finalized: { tone: 'success', variant: 'filled-soft' },
}

export function gradeSheetChip(status: string, label: string): StatusChipProps {
  const { tone, variant } = SHEET_TONE[status] ?? { tone: 'neutral', variant: 'outlined' }
  return { tone, variant, label }
}

/**
 * A line's own state: recorded, or still blank. This is the smallest possible state machine
 * and deliberately so — the Sheet, never the individual Grade, carries the workflow
 * (ADR-0023). The blank chip is what makes an unfinished roster scannable, and ticket 03's
 * blank-line guard on signing counts exactly these.
 */
export function gradeLineChip(hasGrade: boolean, label: string): StatusChipProps {
  return hasGrade
    ? { tone: 'success', variant: 'outlined', label }
    : { tone: 'neutral', variant: 'outlined', label }
}

/**
 * The **grade colour groups** of design-system.md §10 ("Grade display"): a letter at or above
 * C+ earned its credit and reads as ordinary text; one below C+ but above F counted toward the
 * CGPA without earning anything, and carries the sub-earn warning; an F is an error. Colour is
 * explicitly *secondary* there — the letter itself is always shown, so this is emphasis, never
 * the signal (WCAG 1.4.1).
 *
 * Returned as a group name rather than a colour so the mapping to an accessible shade per
 * colour scheme stays in one place: `.main` is a fill colour, so a label needs `.dark` in
 * light and `.light` in dark, the rule StatusChip and the primary/warning button overrides
 * both follow.
 *
 * AU / W / FW / I are absent on purpose — those are Course Statuses, never Grades (ADR-0023),
 * and §10's rows for them belong to Phase 6's transcript grade-column projection.
 */
export type GradeEmphasis = 'earned' | 'subEarn' | 'fail'

/** Appendix C's `min_grade_per_course`: C+ is 2.50 points, the earn-your-credit threshold. */
const MINIMUM_EARNING_POINTS = 2.5

export function gradeEmphasisFor(points: number | undefined): GradeEmphasis {
  if (points === undefined || points >= MINIMUM_EARNING_POINTS) return 'earned'
  return points > 0 ? 'subEarn' : 'fail'
}
