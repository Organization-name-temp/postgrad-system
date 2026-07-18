import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import ScheduleIcon from '@mui/icons-material/Schedule'
import EventBusyIcon from '@mui/icons-material/EventBusy'
import BlockIcon from '@mui/icons-material/Block'
import type { StatusChipProps } from '../../../shared/ui/StatusChip'

export type CycleState = 'open' | 'upcoming' | 'closed' | 'inactive'

interface CycleLike {
  is_active?: boolean
  is_open?: boolean
  opens_at?: string
}

/**
 * Derive an Admission Cycle's display state from the server's authoritative
 * is_active / is_open flags (display-only — the API stays the authority,
 * ADR-0008). "Open" comes straight from the server; "upcoming" vs "closed"
 * refines a not-open-but-active cycle by whether its window is still ahead.
 */
export function cycleState(cycle: CycleLike, now: Date = new Date()): CycleState {
  if (!cycle.is_active) return 'inactive'
  if (cycle.is_open) return 'open'
  if (cycle.opens_at && new Date(cycle.opens_at).getTime() > now.getTime()) return 'upcoming'
  return 'closed'
}

/**
 * Map a Cycle state onto <StatusChip> props. Colour is never the only signal — a
 * label (and icon) always accompany it (WCAG 1.4.1). Colocated with the admin
 * feature (design-system.md §10: each feature owns its own status→chip map; no
 * status names in shared/ui).
 */
export function cycleStatusChip(
  state: CycleState,
  labels: Record<CycleState, string>,
): StatusChipProps {
  switch (state) {
    case 'open':
      return {
        tone: 'success',
        variant: 'filled-soft',
        label: labels.open,
        icon: <CheckCircleIcon />,
      }
    case 'upcoming':
      return {
        tone: 'info',
        variant: 'filled-soft',
        label: labels.upcoming,
        icon: <ScheduleIcon />,
      }
    case 'closed':
      return { tone: 'neutral', variant: 'outlined', label: labels.closed, icon: <EventBusyIcon /> }
    case 'inactive':
      return { tone: 'neutral', variant: 'outlined', label: labels.inactive, icon: <BlockIcon /> }
  }
}
