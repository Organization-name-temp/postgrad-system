import { IconButton, Stack, TableCell, TableRow, Tooltip } from '@mui/material'
import EditIcon from '@mui/icons-material/Edit'
import ToggleOnIcon from '@mui/icons-material/ToggleOn'
import ToggleOffIcon from '@mui/icons-material/ToggleOff'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListFacultiesQueryKey,
  useActivateAFaculty,
  useDeactivateAFaculty,
} from '../../../shared/api/generated/endpoints'
import type { ListFaculties200DataItem } from '../../../shared/api/generated/endpoints'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { activeStatusChip } from '../statusChips'

interface FacultyRowProps {
  faculty: ListFaculties200DataItem
  canManage: boolean
  onEdit: (faculty: ListFaculties200DataItem) => void
}

/**
 * One Faculty row: code, locale-picked name, and an active/inactive StatusChip.
 * Managers additionally get edit + activate/deactivate actions (deactivate is the
 * only "delete" — Faculties are never hard-deleted, BR-18).
 */
export function FacultyRow({ faculty, canManage, onEdit }: FacultyRowProps) {
  const { t, i18n } = useTranslation('catalog')
  const queryClient = useQueryClient()
  const activate = useActivateAFaculty()
  const deactivate = useDeactivateAFaculty()
  const busy = activate.isPending || deactivate.isPending

  const isActive = faculty.is_active ?? false
  const name = (i18n.language === 'ar' ? faculty.name_ar : faculty.name_en) ?? ''
  const chip = activeStatusChip(isActive, {
    active: t('status.active'),
    inactive: t('status.inactive'),
  })

  const refetch = () => void queryClient.invalidateQueries({ queryKey: getListFacultiesQueryKey() })

  const toggleActive = () => {
    if (faculty.id === undefined) {
      return
    }
    if (isActive) {
      deactivate.mutate({ facultyId: faculty.id }, { onSuccess: refetch })
    } else {
      activate.mutate({ facultyId: faculty.id }, { onSuccess: refetch })
    }
  }

  const toggleLabel = isActive ? t('faculties.deactivate') : t('faculties.activate')

  return (
    <TableRow>
      <TableCell>{faculty.code}</TableCell>
      <TableCell>{name}</TableCell>
      <TableCell>
        <StatusChip {...chip} />
      </TableCell>
      {canManage && (
        <TableCell align="right">
          <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
            <Tooltip title={t('faculties.editAction')}>
              <IconButton
                size="small"
                onClick={() => onEdit(faculty)}
                aria-label={t('faculties.editAction')}
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
