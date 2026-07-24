import type { StatusChipProps, StatusTone, StatusChipVariant } from '../../shared/ui/StatusChip'

/**
 * Maps an Enrollment's Course Status onto <StatusChip> props, per design-system.md §10
 * (Course Status tones). Colour is never the only signal — the label always accompanies it
 * (WCAG 1.4.1); the caller passes the already-localised label. The map lives at the feature
 * root, the way each feature colocates its own status→chip map (no status names in
 * shared/ui). Only Regular / Retake / Audit are reachable in Phase 3 (W from ticket 07); the
 * rest are mapped up front so later phases render a stable chip.
 */
const TONE: Record<string, { tone: StatusTone; variant: StatusChipVariant }> = {
  Regular: { tone: 'neutral', variant: 'outlined' },
  Retake: { tone: 'warning', variant: 'filled-soft' },
  Audit: { tone: 'info', variant: 'outlined' },
  Withdrawn: { tone: 'neutral', variant: 'outlined' },
  CgpaImprovement: { tone: 'info', variant: 'filled-soft' },
  Transfer: { tone: 'secondary', variant: 'outlined' },
  Waive: { tone: 'secondary', variant: 'outlined' },
  FailWithdrawal: { tone: 'error', variant: 'filled' },
  Incomplete: { tone: 'warning', variant: 'filled-soft' },
  Exempted: { tone: 'secondary', variant: 'outlined' },
}

export function courseStatusChip(status: string, label: string): StatusChipProps {
  const { tone, variant } = TONE[status] ?? { tone: 'neutral', variant: 'outlined' }
  return { tone, variant, label }
}
