import { IconButton, Stack, TableCell, TableRow, Tooltip } from '@mui/material'
import EditIcon from '@mui/icons-material/Edit'
import ToggleOnIcon from '@mui/icons-material/ToggleOn'
import ToggleOffIcon from '@mui/icons-material/ToggleOff'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListProgramsQueryKey,
  useActivateAProgram,
  useDeactivateAProgram,
} from '../../../shared/api/generated/endpoints'
import type { ListPrograms200DataItem } from '../../../shared/api/generated/endpoints'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { activeStatusChip } from '../statusChips'
import { pickLocaleName } from '../localeName'

interface ProgramRowProps {
  program: ListPrograms200DataItem
  /** The owning Department's name in the active locale (resolved by the page). */
  departmentName: string
  canManage: boolean
  onEdit: (program: ListPrograms200DataItem) => void
}

/**
 * One Program row: code, locale-picked name, owning Department, degree level, the
 * per-Category credit split (prep / core / elective · total), and an
 * active/inactive StatusChip. Managers additionally get edit + activate/deactivate
 * actions (deactivate is the only "delete" — never hard-deleted, BR-18).
 */
export function ProgramRow({ program, departmentName, canManage, onEdit }: ProgramRowProps) {
  const { t, i18n } = useTranslation('catalog')
  const queryClient = useQueryClient()
  const activate = useActivateAProgram()
  const deactivate = useDeactivateAProgram()
  const busy = activate.isPending || deactivate.isPending

  const isActive = program.is_active ?? false
  const name = pickLocaleName(program, i18n.language)
  const degreeLabel = program.degree_level ? t(`programs.degreeLevels.${program.degree_level}`) : ''
  const credits = t('programs.creditSplit', {
    prep: program.prep_required_ch ?? 0,
    core: program.core_required_ch ?? 0,
    elective: program.elective_required_ch ?? 0,
    total: program.total_required_ch ?? 0,
  })
  const chip = activeStatusChip(isActive, {
    active: t('status.active'),
    inactive: t('status.inactive'),
  })

  const refetch = () => void queryClient.invalidateQueries({ queryKey: getListProgramsQueryKey() })

  const toggleActive = () => {
    if (program.id === undefined) {
      return
    }
    if (isActive) {
      deactivate.mutate({ programId: program.id }, { onSuccess: refetch })
    } else {
      activate.mutate({ programId: program.id }, { onSuccess: refetch })
    }
  }

  const toggleLabel = isActive ? t('programs.deactivate') : t('programs.activate')

  return (
    <TableRow>
      <TableCell>{program.code}</TableCell>
      <TableCell>{name}</TableCell>
      <TableCell>{departmentName}</TableCell>
      <TableCell>{degreeLabel}</TableCell>
      <TableCell>{credits}</TableCell>
      <TableCell>
        <StatusChip {...chip} />
      </TableCell>
      {canManage && (
        <TableCell align="right">
          <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
            <Tooltip title={t('programs.editAction')}>
              <IconButton
                size="small"
                onClick={() => onEdit(program)}
                aria-label={t('programs.editAction')}
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
