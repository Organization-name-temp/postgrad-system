import type { StatusChipProps, StatusTone, StatusChipVariant } from '../../shared/ui/StatusChip'

/**
 * Maps the two Course Statuses My Grades ever shows without a Grade — Incomplete and Fail
 * Withdrawal (ticket 05) — onto <StatusChip> props, per design-system.md §10 (Course Status
 * tones). The `features/enrollment` courseStatusChip already maps the full ten-value set for
 * My Schedule, but a feature may not import another feature's module (the ESLint boundaries
 * rule), so this colocates only the two values Grading itself ever renders: every other row on
 * My Grades carries a Grade and reads its letter instead of a status chip.
 */
const TONE: Record<string, { tone: StatusTone; variant: StatusChipVariant }> = {
  Incomplete: { tone: 'warning', variant: 'filled-soft' },
  FailWithdrawal: { tone: 'error', variant: 'filled' },
}

export function courseStatusChip(status: string, label: string): StatusChipProps {
  const { tone, variant } = TONE[status] ?? { tone: 'neutral', variant: 'outlined' }
  return { tone, variant, label }
}

/**
 * Whether a Course Status renders as this chip rather than a Grade's letter — read off the
 * same map `courseStatusChip` does, so the two can never name a different set between them.
 */
export function isUngradedVisibleStatus(status: string | undefined): boolean {
  return status !== undefined && status in TONE
}
