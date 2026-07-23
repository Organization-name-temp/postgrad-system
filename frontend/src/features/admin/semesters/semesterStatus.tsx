import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import ScheduleIcon from '@mui/icons-material/Schedule'
import PlayCircleIcon from '@mui/icons-material/PlayCircle'
import EventBusyIcon from '@mui/icons-material/EventBusy'
import type { StatusChipProps } from '../../../shared/ui/StatusChip'

export type SemesterState = 'upcoming' | 'registration' | 'running' | 'ended'

interface SemesterLike {
  registration_opens_at?: string
  registration_closes_at?: string
  starts_at?: string
  ends_at?: string
}

/**
 * Derive a Semester's display state from its period dates (display-only — the API
 * stays the authority on the raw facts, ADR-0008). The precedence — ended, then
 * running, then registration — mirrors the backend SemesterCalendar so a row and the
 * server never disagree. "Registration open" reflects the *actual* registration
 * window [opens, closes] (the server's `is_registration_open` predicate expressed in
 * dates), so a term whose registration has closed but not yet started reads
 * "upcoming", never a stale "registration open".
 */
export function semesterState(semester: SemesterLike, now: Date = new Date()): SemesterState {
  const t = now.getTime()
  const ends = semester.ends_at ? new Date(semester.ends_at).getTime() : undefined
  const starts = semester.starts_at ? new Date(semester.starts_at).getTime() : undefined
  const regOpens = semester.registration_opens_at
    ? new Date(semester.registration_opens_at).getTime()
    : undefined
  const regCloses = semester.registration_closes_at
    ? new Date(semester.registration_closes_at).getTime()
    : undefined

  if (ends !== undefined && t > ends) return 'ended'
  if (starts !== undefined && t >= starts) return 'running'
  if (regOpens !== undefined && regCloses !== undefined && t >= regOpens && t <= regCloses)
    return 'registration'
  return 'upcoming'
}

/**
 * Map a Semester state onto <StatusChip> props. Colour is never the only signal — a
 * label (and icon) always accompany it (WCAG 1.4.1). Colocated with the admin
 * feature (design-system.md §10: each feature owns its own status→chip map; no
 * status names in shared/ui).
 */
export function semesterStatusChip(
  state: SemesterState,
  labels: Record<SemesterState, string>,
): StatusChipProps {
  switch (state) {
    case 'registration':
      return {
        tone: 'success',
        variant: 'filled-soft',
        label: labels.registration,
        icon: <CheckCircleIcon />,
      }
    case 'running':
      return {
        tone: 'info',
        variant: 'filled-soft',
        label: labels.running,
        icon: <PlayCircleIcon />,
      }
    case 'upcoming':
      return {
        tone: 'secondary',
        variant: 'filled-soft',
        label: labels.upcoming,
        icon: <ScheduleIcon />,
      }
    case 'ended':
      return { tone: 'neutral', variant: 'outlined', label: labels.ended, icon: <EventBusyIcon /> }
  }
}
