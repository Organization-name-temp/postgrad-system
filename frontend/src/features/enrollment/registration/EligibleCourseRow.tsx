import { Stack, TableCell, TableRow, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import type { EligibleCourse } from './classification'

interface EligibleCourseRowProps {
  course: EligibleCourse
}

/**
 * One eligible-course line: the resolved course (code + locale name), its instructor,
 * the weekly meeting slots, the seats still available, and the credit hours the cart
 * will sum (ticket 05). Read-only in this ticket — no cart controls yet.
 */
export function EligibleCourseRow({ course }: EligibleCourseRowProps) {
  const { t, i18n } = useTranslation('enrollment')

  const catalogCourse = course.course
  const courseName = catalogCourse ? pickLocaleName(catalogCourse, i18n.language) : ''
  const meetings = course.meetings ?? []

  return (
    <TableRow hover>
      <TableCell>
        <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
          {catalogCourse?.code}
        </Typography>{' '}
        {courseName}
      </TableCell>
      <TableCell>{course.instructor?.name}</TableCell>
      <TableCell>
        {meetings.length === 0 ? (
          <Typography component="span" variant="body2" color="text.secondary">
            {t('noMeetings')}
          </Typography>
        ) : (
          <Stack spacing={0.25}>
            {meetings.map((meeting) => (
              <Typography key={`${meeting.weekday}-${meeting.starts_at}`} variant="body2">
                {t(`weekdays.${meeting.weekday}`)} {meeting.starts_at}–{meeting.ends_at}
              </Typography>
            ))}
          </Stack>
        )}
      </TableCell>
      <TableCell align="right">
        {course.seats_remaining == null ? (
          <Typography component="span" variant="body2" color="text.secondary">
            {t('uncapped')}
          </Typography>
        ) : (
          course.seats_remaining
        )}
      </TableCell>
      <TableCell align="right">{course.total_ch}</TableCell>
    </TableRow>
  )
}
