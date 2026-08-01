import { useState } from 'react'
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
import {
  useTheStudentsTheComposerMayRegisterOnBehalfOf,
  useViewAnyStudentsScheduleoversight,
} from '../../../shared/api/generated/endpoints'
import type {
  TheStudentsTheComposerMayRegisterOnBehalfOf200DataItem as ComposableStudent,
  ViewAnyStudentsScheduleoversight200DataItem as ScheduleGroup,
} from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { courseStatusChip } from '../courseStatusChip'
import { MeetingTimes } from '../MeetingTimes'
import { mustRetakeChip } from '../mustRetakeChip'
import { AttendanceCell } from '../schedule/AttendanceCell'
import { StudentPicker } from '../on-behalf/StudentPicker'

/**
 * The student-schedule oversight view (features/enrollment, ticket 10, ADR-0015). A reviewer
 * looks up a student and sees their minted Enrollments grouped by Semester — read-only:
 * Course Status, classification, meeting times, credit hours and derived attendance, with no
 * add/drop/withdraw actions (those are the student's own). It reuses the register-for-student
 * {@link StudentPicker}, whose population the server already scopes the same way this surface
 * needs: an Advisor sees their advisees (an incidental start on user story 51), Student
 * Affairs and an administrator any student. Gated on `registration.approve` (nav + API); the
 * schedule read re-enforces the advisee scope, so an advisor reading a non-advisee is a 403.
 */
export function StudentSchedulePage() {
  const { t, i18n } = useTranslation('enrollment')
  const [student, setStudent] = useState<ComposableStudent | null>(null)

  const studentsQuery = useTheStudentsTheComposerMayRegisterOnBehalfOf()
  const students = studentsQuery.data?.data ?? []

  const studentId = student?.student_id ?? null
  const scheduleQuery = useViewAnyStudentsScheduleoversight(studentId ?? 0, {
    query: { enabled: studentId != null },
  })
  const groups = scheduleQuery.data?.data ?? []

  return (
    <>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('studentSchedule.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
        {t('studentSchedule.intro')}
      </Typography>

      {studentsQuery.isPending && <CircularProgress aria-label={t('onBehalf.loadingStudents')} />}
      {studentsQuery.isError && <Alert severity="error">{t('onBehalf.loadStudentsError')}</Alert>}

      {!studentsQuery.isPending && !studentsQuery.isError && (
        <Stack spacing={3}>
          <StudentPicker students={students} value={student} onChange={setStudent} />

          {student && (
            <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 2, sm: 3 } }}>
              <Typography variant="h6" component="p">
                {student.name}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {student.university_id}
                {student.program &&
                  ` · ${pickLocaleName(student.program, i18n.language, student.program.code ?? '')}`}
              </Typography>
            </Paper>
          )}

          {student && scheduleQuery.isPending && (
            <CircularProgress aria-label={t('schedule.loading')} />
          )}
          {student && scheduleQuery.isError && (
            <Alert severity="error">{t('schedule.loadError')}</Alert>
          )}
          {student && !scheduleQuery.isPending && !scheduleQuery.isError && groups.length === 0 && (
            <Typography color="text.secondary">{t('studentSchedule.empty')}</Typography>
          )}

          {student &&
            groups.map((group, index) => (
              <SemesterCard key={group.semester?.id ?? index} group={group} />
            ))}
        </Stack>
      )}
    </>
  )
}

/** One Semester's Enrollments as a read-only card — no period banner, no per-row actions. */
function SemesterCard({ group }: { group: ScheduleGroup }) {
  const { t, i18n } = useTranslation('enrollment')
  const semester = group.semester
  const enrollments = group.enrollments ?? []
  const headingId = `student-semester-${semester?.id ?? 'x'}-heading`
  const semesterName = semester ? pickLocaleName(semester, i18n.language) : ''
  const totalCredits = enrollments.reduce((sum, e) => sum + (e.credit_hours ?? 0), 0)

  return (
    <Paper
      component="section"
      aria-labelledby={headingId}
      variant="outlined"
      sx={{ borderRadius: 3, p: { xs: 2, sm: 3 } }}
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
              <TableCell>{t('schedule.columns.attendance')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {enrollments.map((enrollment) => {
              const course = enrollment.course
              const name = course ? pickLocaleName(course, i18n.language) : ''
              const status = enrollment.course_status ?? 'Regular'

              return (
                <TableRow key={enrollment.id}>
                  <TableCell>
                    <Stack spacing={0.75} sx={{ alignItems: 'flex-start' }}>
                      <Typography variant="body2">
                        <Typography component="span" variant="body2" sx={{ fontWeight: 600 }}>
                          {course?.code}
                        </Typography>{' '}
                        {name}
                      </Typography>
                      {enrollment.must_retake && (
                        <StatusChip {...mustRetakeChip(t('mustRetake.label'))} />
                      )}
                    </Stack>
                  </TableCell>
                  <TableCell>
                    <StatusChip {...courseStatusChip(status, t(`courseStatus.${status}`))} />
                  </TableCell>
                  <TableCell>
                    {enrollment.classification
                      ? t(`classifications.${enrollment.classification}`)
                      : ''}
                  </TableCell>
                  <TableCell>
                    <MeetingTimes meetings={enrollment.meetings ?? []} />
                  </TableCell>
                  <TableCell align="right">{enrollment.credit_hours}</TableCell>
                  <TableCell>
                    <AttendanceCell attendance={enrollment.attendance} />
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </TableContainer>
    </Paper>
  )
}
