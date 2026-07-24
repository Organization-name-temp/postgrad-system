import {
  Chip,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import type { TheActingStudentsRegistrationRequestsForTheOpenSemester200DataRequestsItem } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'

/** A submitted registration request as the read endpoint returns it. */
export type RegistrationRequestView =
  TheActingStudentsRegistrationRequestsForTheOpenSemester200DataRequestsItem

interface RequestViewProps {
  request: RegistrationRequestView
}

/** The MUI Chip colour for each request status. */
const STATUS_COLOR: Record<string, 'warning' | 'success' | 'error'> = {
  Pending: 'warning',
  Approved: 'success',
  Rejected: 'error',
}

/**
 * A read-only view of one submitted registration request (user story 30): its status in
 * the advisor pipeline, when it was submitted, the advisor's note once decided (ticket 06),
 * and its per-line detail — course, type, mode, meeting times and credit hours. Used both
 * for the Pending request the student is waiting on and for a Rejected one shown above a
 * fresh cart. The heading is a real `<h2>` landmark (axe-clean).
 */
export function RequestView({ request }: RequestViewProps) {
  const { t, i18n } = useTranslation('enrollment')
  const headingId = `request-${request.id}-heading`

  const status = request.status ?? 'Pending'
  const submittedAt = request.submitted_at
    ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'long' }).format(
        new Date(request.submitted_at),
      )
    : ''
  const lines = request.lines ?? []

  return (
    <Paper
      component="section"
      aria-labelledby={headingId}
      variant="outlined"
      sx={{ borderRadius: 3, p: { xs: 2, sm: 3 }, mb: 3 }}
    >
      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        spacing={1}
        sx={{ justifyContent: 'space-between', alignItems: { sm: 'center' }, mb: 2 }}
      >
        <Stack spacing={0.25}>
          <Typography id={headingId} variant="h6" component="h2">
            {t('request.heading')}
          </Typography>
          {submittedAt && (
            <Typography variant="body2" color="text.secondary">
              {t('request.submittedOn', { date: submittedAt })}
            </Typography>
          )}
        </Stack>
        <Chip color={STATUS_COLOR[status] ?? 'default'} label={t(`request.status.${status}`)} />
      </Stack>

      {request.advisor_note && (
        <Typography variant="body2" sx={{ mb: 2 }}>
          <Typography component="span" variant="body2" sx={{ fontWeight: 600 }}>
            {t('request.advisorNote')}:
          </Typography>{' '}
          {request.advisor_note}
        </Typography>
      )}

      {lines.length === 0 ? (
        <Typography color="text.secondary">{t('request.empty')}</Typography>
      ) : (
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>{t('request.columns.course')}</TableCell>
                <TableCell>{t('request.columns.classification')}</TableCell>
                <TableCell>{t('request.columns.mode')}</TableCell>
                <TableCell>{t('request.columns.meetings')}</TableCell>
                <TableCell align="right">{t('request.columns.creditHours')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {lines.map((line) => {
                const course = line.course
                const name = course ? pickLocaleName(course, i18n.language) : ''
                const meetings = line.meetings ?? []

                return (
                  <TableRow key={line.offering_id}>
                    <TableCell>
                      <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
                        {course?.code}
                      </Typography>{' '}
                      {name}
                    </TableCell>
                    <TableCell>
                      {line.classification ? t(`classifications.${line.classification}`) : ''}
                    </TableCell>
                    <TableCell>{line.mode ? t(`request.mode.${line.mode}`) : ''}</TableCell>
                    <TableCell>
                      {meetings.length === 0 ? (
                        <Typography component="span" variant="body2" color="text.secondary">
                          {t('noMeetings')}
                        </Typography>
                      ) : (
                        <Stack spacing={0.25}>
                          {meetings.map((meeting) => (
                            <Typography
                              key={`${meeting.weekday}-${meeting.starts_at}`}
                              variant="body2"
                            >
                              {t(`weekdays.${meeting.weekday}`)} {meeting.starts_at}–
                              {meeting.ends_at}
                            </Typography>
                          ))}
                        </Stack>
                      )}
                    </TableCell>
                    <TableCell align="right">{line.total_ch}</TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        </TableContainer>
      )}
    </Paper>
  )
}
