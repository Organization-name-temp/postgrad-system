import { IconButton, Stack, TableCell, TableRow, Tooltip } from '@mui/material'
import EditIcon from '@mui/icons-material/Edit'
import ToggleOnIcon from '@mui/icons-material/ToggleOn'
import ToggleOffIcon from '@mui/icons-material/ToggleOff'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListCoursesQueryKey,
  useActivateACourse,
  useDeactivateACourse,
} from '../../../shared/api/generated/endpoints'
import type { ListCourses200DataItem } from '../../../shared/api/generated/endpoints'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { activeStatusChip } from '../statusChips'
import { pickLocaleName } from '../localeName'

interface CourseRowProps {
  course: ListCourses200DataItem
  /**
   * The owning Department's name in the active locale (resolved by the page), or
   * undefined when the Course is university-wide (department_id is null).
   */
  departmentName?: string
  canManage: boolean
  onEdit: (course: ListCourses200DataItem) => void
}

/**
 * One Course row: code, locale-picked name, owning Department (or "university-wide"
 * when it has none), level, the Lecture/Tutorial/Lab · Total credit-hour split, and
 * an active/inactive StatusChip. Managers additionally get edit +
 * activate/deactivate actions (deactivate is the only "delete" — never
 * hard-deleted, BR-18 / FR-PCM-003.3).
 */
export function CourseRow({ course, departmentName, canManage, onEdit }: CourseRowProps) {
  const { t, i18n } = useTranslation('catalog')
  const queryClient = useQueryClient()
  const activate = useActivateACourse()
  const deactivate = useDeactivateACourse()
  const busy = activate.isPending || deactivate.isPending

  const isActive = course.is_active ?? false
  const name = pickLocaleName(course, i18n.language)
  const owner = course.department_id != null ? departmentName : t('courses.universityWide')
  const credits = t('courses.creditSplit', {
    lecture: course.lecture_ch ?? 0,
    tutorial: course.tutorial_ch ?? 0,
    lab: course.lab_ch ?? 0,
    total: course.total_ch ?? 0,
  })
  const chip = activeStatusChip(isActive, {
    active: t('status.active'),
    inactive: t('status.inactive'),
  })

  const refetch = () => void queryClient.invalidateQueries({ queryKey: getListCoursesQueryKey() })

  const toggleActive = () => {
    if (course.id === undefined) {
      return
    }
    if (isActive) {
      deactivate.mutate({ courseId: course.id }, { onSuccess: refetch })
    } else {
      activate.mutate({ courseId: course.id }, { onSuccess: refetch })
    }
  }

  const toggleLabel = isActive ? t('courses.deactivate') : t('courses.activate')

  return (
    <TableRow>
      <TableCell>{course.code}</TableCell>
      <TableCell>{name}</TableCell>
      <TableCell>{owner}</TableCell>
      <TableCell>{course.level}</TableCell>
      <TableCell>{credits}</TableCell>
      <TableCell>
        <StatusChip {...chip} />
      </TableCell>
      {canManage && (
        <TableCell align="right">
          <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
            <Tooltip title={t('courses.editAction')}>
              <IconButton
                size="small"
                onClick={() => onEdit(course)}
                aria-label={t('courses.editAction')}
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
