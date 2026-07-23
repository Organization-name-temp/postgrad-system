import { IconButton, TableCell, TableRow, Tooltip } from '@mui/material'
import EditIcon from '@mui/icons-material/Edit'
import { useTranslation } from 'react-i18next'
import type { ListSemesters200DataItem } from '../../../shared/api/generated/endpoints'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { formatDate } from '../../../shared/datetime'
import { semesterState, semesterStatusChip } from './semesterStatus'

interface SemesterRowProps {
  semester: ListSemesters200DataItem
  canManage: boolean
  onEdit: (semester: ListSemesters200DataItem) => void
}

/**
 * One Semester row: locale-picked name, term, year, the semester window and the
 * registration window (locale-formatted) and a derived-state StatusChip. Managers
 * additionally get edit — the only write on a Semester (they are never deleted; the
 * records built on them must survive, ADR-0018).
 */
export function SemesterRow({ semester, canManage, onEdit }: SemesterRowProps) {
  const { t, i18n } = useTranslation('admin')

  const name = pickLocaleName(semester, i18n.language)
  const term = semester.term ? t(`semesters.terms.${semester.term}`, semester.term) : ''
  const semesterWindow = `${formatDate(semester.starts_at, i18n.language)} – ${formatDate(semester.ends_at, i18n.language)}`
  const registrationWindow = `${formatDate(semester.registration_opens_at, i18n.language)} – ${formatDate(semester.registration_closes_at, i18n.language)}`

  const state = semesterState(semester)
  const chip = semesterStatusChip(state, {
    upcoming: t('semesters.status.upcoming'),
    registration: t('semesters.status.registration'),
    running: t('semesters.status.running'),
    ended: t('semesters.status.ended'),
  })

  return (
    <TableRow>
      <TableCell>{name}</TableCell>
      <TableCell>{term}</TableCell>
      <TableCell>{semester.year}</TableCell>
      <TableCell>{semesterWindow}</TableCell>
      <TableCell>{registrationWindow}</TableCell>
      <TableCell>
        <StatusChip {...chip} />
      </TableCell>
      {canManage && (
        <TableCell align="right">
          <Tooltip title={t('semesters.editAction')}>
            <IconButton
              size="small"
              onClick={() => onEdit(semester)}
              aria-label={t('semesters.editAction')}
            >
              <EditIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        </TableCell>
      )}
    </TableRow>
  )
}
