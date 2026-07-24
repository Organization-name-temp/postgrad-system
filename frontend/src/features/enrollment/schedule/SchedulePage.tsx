import {
  Alert,
  CircularProgress,
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
import { useTheActingStudentsScheduleTheirMintedEnrollmentsGroupedBySemester } from '../../../shared/api/generated/endpoints'
import type {
  TheActingStudentsScheduleTheirMintedEnrollmentsGroupedBySemester200DataItem as ScheduleGroup,
  TheActingStudentsScheduleTheirMintedEnrollmentsGroupedBySemester200DataItemEnrollmentsItem as ScheduleEnrollment,
} from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { courseStatusChip } from '../courseStatusChip'
import { MeetingTimes } from '../MeetingTimes'

/**
 * The Student's My Schedule (features/enrollment, ticket 06): their minted Enrollments
 * grouped by Semester, each row showing the Course Status chip, Enrollment Classification,
 * meeting times and registration-time credit hours (user story 32). The period-gated action
 * buttons (add/drop/withdraw) and attendance % arrive in tickets 07/08; here it is a
 * read-only present. Persona-guarded to R02; the API re-enforces `courses.register_self`.
 */
export function SchedulePage() {
  const { t } = useTranslation('enrollment')
  const { data, isPending, isError } =
    useTheActingStudentsScheduleTheirMintedEnrollmentsGroupedBySemester()
  const groups = data?.data ?? []

  return (
    <>
      <Typography variant="h4" component="h1" sx={{ mb: 3 }}>
        {t('schedule.title')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('schedule.loading')} />}
      {isError && <Alert severity="error">{t('schedule.loadError')}</Alert>}

      {!isPending && !isError && groups.length === 0 && (
        <Typography color="text.secondary">{t('schedule.empty')}</Typography>
      )}

      {groups.map((group, index) => (
        <SemesterCard key={group.semester?.id ?? index} group={group} />
      ))}
    </>
  )
}

/** One Semester's Enrollments as a card with a table — a labelled landmark (axe-clean). */
function SemesterCard({ group }: { group: ScheduleGroup }) {
  const { t, i18n } = useTranslation('enrollment')
  const semester = group.semester
  const enrollments = group.enrollments ?? []
  const headingId = `semester-${semester?.id ?? 'x'}-heading`
  const semesterName = semester ? pickLocaleName(semester, i18n.language) : ''
  const totalCredits = enrollments.reduce((sum, e) => sum + (e.credit_hours ?? 0), 0)

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
        <Typography id={headingId} variant="h6" component="h2">
          {semesterName}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {t('schedule.totalCredits', { count: totalCredits })}
        </Typography>
      </Stack>

      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>{t('schedule.columns.course')}</TableCell>
              <TableCell>{t('schedule.columns.status')}</TableCell>
              <TableCell>{t('schedule.columns.classification')}</TableCell>
              <TableCell>{t('schedule.columns.meetings')}</TableCell>
              <TableCell align="right">{t('schedule.columns.creditHours')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {enrollments.map((enrollment) => (
              <EnrollmentRow key={enrollment.id} enrollment={enrollment} />
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Paper>
  )
}

function EnrollmentRow({ enrollment }: { enrollment: ScheduleEnrollment }) {
  const { t, i18n } = useTranslation('enrollment')
  const course = enrollment.course
  const name = course ? pickLocaleName(course, i18n.language) : ''
  const meetings = enrollment.meetings ?? []
  const status = enrollment.course_status ?? 'Regular'

  return (
    <TableRow>
      <TableCell>
        <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
          {course?.code}
        </Typography>{' '}
        {name}
      </TableCell>
      <TableCell>
        <StatusChip {...courseStatusChip(status, t(`courseStatus.${status}`))} />
      </TableCell>
      <TableCell>
        {enrollment.classification ? t(`classifications.${enrollment.classification}`) : ''}
      </TableCell>
      <TableCell>
        <MeetingTimes meetings={meetings} />
      </TableCell>
      <TableCell align="right">{enrollment.credit_hours}</TableCell>
    </TableRow>
  )
}
