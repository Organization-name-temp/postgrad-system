import { IconButton, Stack, TableCell, TableRow, Tooltip } from '@mui/material'
import EditIcon from '@mui/icons-material/Edit'
import ToggleOnIcon from '@mui/icons-material/ToggleOn'
import ToggleOffIcon from '@mui/icons-material/ToggleOff'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListDepartmentsQueryKey,
  useActivateADepartment,
  useDeactivateADepartment,
} from '../../../shared/api/generated/endpoints'
import type { ListDepartments200DataItem } from '../../../shared/api/generated/endpoints'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { activeStatusChip } from '../statusChips'
import { pickLocaleName } from '../localeName'

interface DepartmentRowProps {
  department: ListDepartments200DataItem
  /** The owning Faculty's name in the active locale (resolved by the page). */
  facultyName: string
  canManage: boolean
  onEdit: (department: ListDepartments200DataItem) => void
}

/**
 * One Department row: code, locale-picked name, owning Faculty, and an
 * active/inactive StatusChip. Managers additionally get edit + activate/deactivate
 * actions (deactivate is the only "delete" — never hard-deleted, BR-18).
 */
export function DepartmentRow({ department, facultyName, canManage, onEdit }: DepartmentRowProps) {
  const { t, i18n } = useTranslation('catalog')
  const queryClient = useQueryClient()
  const activate = useActivateADepartment()
  const deactivate = useDeactivateADepartment()
  const busy = activate.isPending || deactivate.isPending

  const isActive = department.is_active ?? false
  const name = pickLocaleName(department, i18n.language)
  const chip = activeStatusChip(isActive, {
    active: t('status.active'),
    inactive: t('status.inactive'),
  })

  const refetch = () =>
    void queryClient.invalidateQueries({ queryKey: getListDepartmentsQueryKey() })

  const toggleActive = () => {
    if (department.id === undefined) {
      return
    }
    if (isActive) {
      deactivate.mutate({ departmentId: department.id }, { onSuccess: refetch })
    } else {
      activate.mutate({ departmentId: department.id }, { onSuccess: refetch })
    }
  }

  const toggleLabel = isActive ? t('departments.deactivate') : t('departments.activate')

  return (
    <TableRow>
      <TableCell>{department.code}</TableCell>
      <TableCell>{name}</TableCell>
      <TableCell>{facultyName}</TableCell>
      <TableCell>
        <StatusChip {...chip} />
      </TableCell>
      {canManage && (
        <TableCell align="right">
          <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
            <Tooltip title={t('departments.editAction')}>
              <IconButton
                size="small"
                onClick={() => onEdit(department)}
                aria-label={t('departments.editAction')}
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
