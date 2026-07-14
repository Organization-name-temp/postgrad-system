import { IconButton, Stack, TableCell, TableRow, Tooltip } from '@mui/material'
import EditIcon from '@mui/icons-material/Edit'
import ToggleOnIcon from '@mui/icons-material/ToggleOn'
import ToggleOffIcon from '@mui/icons-material/ToggleOff'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListAdmissionCyclesQueryKey,
  useActivateAnAdmissionCycle,
  useDeactivateAnAdmissionCycle,
} from '../../../shared/api/generated/endpoints'
import type { ListAdmissionCycles200DataItem } from '../../../shared/api/generated/endpoints'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { cycleState, cycleStatusChip } from './cycleStatus'
import { formatDate } from './dateTime'

interface CycleRowProps {
  cycle: ListAdmissionCycles200DataItem
  canManage: boolean
  onEdit: (cycle: ListAdmissionCycles200DataItem) => void
}

/**
 * One Admission Cycle row: locale-picked name, intake term, year, the window
 * (opens–closes, locale-formatted) and a derived-status StatusChip. Managers
 * additionally get edit + activate/deactivate — deactivate is the only "delete"
 * (cycles are never hard-deleted; their Applications must survive).
 */
export function CycleRow({ cycle, canManage, onEdit }: CycleRowProps) {
  const { t, i18n } = useTranslation('admin')
  const queryClient = useQueryClient()
  const activate = useActivateAnAdmissionCycle()
  const deactivate = useDeactivateAnAdmissionCycle()
  const busy = activate.isPending || deactivate.isPending

  const isActive = cycle.is_active ?? false
  const name = pickLocaleName(cycle, i18n.language)
  const term = cycle.intake_term ? t(`cycles.terms.${cycle.intake_term}`, cycle.intake_term) : ''
  const windowLabel = `${formatDate(cycle.opens_at, i18n.language)} – ${formatDate(cycle.closes_at, i18n.language)}`

  const state = cycleState(cycle)
  const chip = cycleStatusChip(state, {
    open: t('cycles.status.open'),
    upcoming: t('cycles.status.upcoming'),
    closed: t('cycles.status.closed'),
    inactive: t('cycles.status.inactive'),
  })

  const refetch = () =>
    void queryClient.invalidateQueries({ queryKey: getListAdmissionCyclesQueryKey() })

  const toggleActive = () => {
    if (cycle.id === undefined) {
      return
    }
    if (isActive) {
      deactivate.mutate({ admissionCycleId: cycle.id }, { onSuccess: refetch })
    } else {
      activate.mutate({ admissionCycleId: cycle.id }, { onSuccess: refetch })
    }
  }

  const toggleLabel = isActive ? t('cycles.deactivate') : t('cycles.activate')

  return (
    <TableRow>
      <TableCell>{name}</TableCell>
      <TableCell>{term}</TableCell>
      <TableCell>{cycle.year}</TableCell>
      <TableCell>{windowLabel}</TableCell>
      <TableCell>
        <StatusChip {...chip} />
      </TableCell>
      {canManage && (
        <TableCell align="right">
          <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
            <Tooltip title={t('cycles.editAction')}>
              <IconButton
                size="small"
                onClick={() => onEdit(cycle)}
                aria-label={t('cycles.editAction')}
              >
                <EditIcon fontSize="small" />
              </IconButton>
            </Tooltip>
            <Tooltip title={toggleLabel}>
              <span>
                <IconButton
                  size="small"
                  onClick={toggleActive}
                  disabled={busy}
                  aria-label={toggleLabel}
                >
                  {isActive ? (
                    <ToggleOffIcon fontSize="small" />
                  ) : (
                    <ToggleOnIcon fontSize="small" />
                  )}
                </IconButton>
              </span>
            </Tooltip>
          </Stack>
        </TableCell>
      )}
    </TableRow>
  )
}
