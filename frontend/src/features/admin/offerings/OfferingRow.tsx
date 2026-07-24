import { IconButton, Stack, TableCell, TableRow, Tooltip, Typography } from '@mui/material'
import EditIcon from '@mui/icons-material/Edit'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlined'
import { useTranslation } from 'react-i18next'
import type { ListASemestersCourseOfferings200DataItem } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { formatMeetingRange } from './meetingTime'

interface OfferingRowProps {
  offering: ListASemestersCourseOfferings200DataItem
  canManage: boolean
  onEdit: (offering: ListASemestersCourseOfferings200DataItem) => void
  onDelete: (offering: ListASemestersCourseOfferings200DataItem) => void
}

/**
 * One Course Offering row: the resolved course (code + locale name), the instructor's
 * name, the seat capacity (or "uncapped"), and the weekly meeting slots. Managers get
 * edit and delete — delete is refused by the API once the Offering is referenced.
 */
export function OfferingRow({ offering, canManage, onEdit, onDelete }: OfferingRowProps) {
  const { t, i18n } = useTranslation('admin')

  const course = offering.course
  const courseName = course ? pickLocaleName(course, i18n.language) : ''
  const meetings = offering.meetings ?? []

  return (
    <TableRow hover>
      <TableCell>
        <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
          {course?.code}
        </Typography>{' '}
        {courseName}
      </TableCell>
      <TableCell>{offering.instructor?.name}</TableCell>
      <TableCell>
        {offering.capacity ?? (
          <Typography component="span" variant="body2" color="text.secondary">
            {t('offerings.uncapped')}
          </Typography>
        )}
      </TableCell>
      <TableCell>
        <Stack spacing={0.25}>
          {meetings.map((meeting) => (
            <Typography key={meeting.id} variant="body2">
              {t(`offerings.weekdays.${meeting.weekday}`)}{' '}
              {formatMeetingRange(meeting.starts_at, meeting.ends_at)}
            </Typography>
          ))}
        </Stack>
      </TableCell>
      {canManage && (
        <TableCell align="right">
          <Tooltip title={t('offerings.editAction')}>
            <IconButton
              size="small"
              onClick={() => onEdit(offering)}
              aria-label={t('offerings.editAction')}
            >
              <EditIcon fontSize="small" />
            </IconButton>
          </Tooltip>
          <Tooltip title={t('offerings.deleteAction')}>
            <IconButton
              size="small"
              color="error"
              onClick={() => onDelete(offering)}
              aria-label={t('offerings.deleteAction')}
            >
              <DeleteOutlineIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        </TableCell>
      )}
    </TableRow>
  )
}
