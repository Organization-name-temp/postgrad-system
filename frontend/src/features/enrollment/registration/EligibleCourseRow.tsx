import { Button, Stack, TableCell, TableRow, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { mustRetakeChip } from '../mustRetakeChip'
import type { EligibleCourse } from './classification'

interface EligibleCourseRowProps {
  course: EligibleCourse
  /** Whether this offering is already in the cart — set only in cart-builder mode. */
  inCart?: boolean
  /** Add/remove this offering from the cart. When omitted, the row is read-only. */
  onToggle?: () => void
}

/**
 * One eligible-course line: the resolved course (code + locale name), its instructor,
 * the weekly meeting slots, the seats still available, and the credit hours the cart
 * sums. In cart-builder mode ({@link onToggle} set) a trailing action adds or removes the
 * offering from the cart; the per-line Regular/Audit choice lives in the cart panel.
 */
export function EligibleCourseRow({ course, inCart, onToggle }: EligibleCourseRowProps) {
  const { t, i18n } = useTranslation('enrollment')

  const catalogCourse = course.course
  const courseName = catalogCourse ? pickLocaleName(catalogCourse, i18n.language) : ''
  const meetings = course.meetings ?? []
  const full = course.seats_remaining != null && course.seats_remaining < 1

  return (
    <TableRow hover>
      <TableCell>
        <Stack spacing={0.75} sx={{ alignItems: 'flex-start' }}>
          <Typography variant="body2">
            <Typography component="span" variant="body2" sx={{ fontWeight: 600 }}>
              {catalogCourse?.code}
            </Typography>{' '}
            {courseName}
          </Typography>
          {course.must_retake && <StatusChip {...mustRetakeChip(t('mustRetake.label'))} />}
        </Stack>
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
      {onToggle && (
        <TableCell align="right">
          <Button
            size="small"
            variant={inCart ? 'contained' : 'outlined'}
            disabled={full && !inCart}
            onClick={onToggle}
            aria-label={t(inCart ? 'cart.removeCourse' : 'cart.addCourse', {
              course: catalogCourse?.code ?? '',
            })}
          >
            {t(inCart ? 'cart.inCart' : 'cart.add')}
          </Button>
        </TableCell>
      )}
    </TableRow>
  )
}
