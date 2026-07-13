import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import BlockIcon from '@mui/icons-material/Block'
import type { StatusChipProps } from '../../shared/ui/StatusChip'

/**
 * Maps a catalog entity's active/inactive flag onto <StatusChip> props. Colour is
 * never the only signal — a label (and icon) always accompany it (WCAG 1.4.1).
 * Faculties, Departments, Programs and Courses all share this active/inactive
 * status, so the map lives at the feature root (design-system.md §10: each
 * feature colocates its own status→chip map; no status names in shared/ui).
 */
export function activeStatusChip(
  isActive: boolean,
  labels: { active: string; inactive: string },
): StatusChipProps {
  return isActive
    ? { tone: 'success', variant: 'filled-soft', label: labels.active, icon: <CheckCircleIcon /> }
    : { tone: 'neutral', variant: 'outlined', label: labels.inactive, icon: <BlockIcon /> }
}
