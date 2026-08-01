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
import { tabularNums } from '../../../app/theme/fonts'
import { useMyGrades } from '../../../shared/api/generated/endpoints'
import type {
  MyGrades200DataSemestersItem as SemesterGroup,
  MyGrades200DataSemestersItemCoursesItem as CourseRow,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { courseStatusChip, isUngradedVisibleStatus } from '../courseStatusChip'
import { formatGradeHistory, readGradeHistory } from '../gradeHistory'
import { GRADE_EMPHASIS_SX, gradeEmphasisFor } from '../gradeSheetChip'

/** A GPA/CGPA figure formatted to two decimals, or the dash before any graded course exists. */
function gpaDisplay(value: number | null | undefined, dash: string): string {
  return typeof value === 'number' ? value.toFixed(2) : dash
}

/** A locale-formatted calendar date for an Incomplete's derived resolution deadline. */
function deadlineDisplay(iso: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(iso))
}

/**
 * My Grades (ADR-0020, ticket 04): the Student's own academic record, closing SRS §4.7.2 —
 * Semester sections read chronologically (newest first, matching the server), each with its
 * own GPA beside its Courses, and the CGPA prominent at the top — the one number the whole
 * degree turns on. Nothing renders here until the Department Head has finalized a Sheet: the
 * server never sends a provisional grade. A superseded earlier Attempt of a retaken Course
 * still appears, muted, with an explicit label — visible on the record but excluded from
 * every average it no longer counts toward.
 *
 * Persona-guarded to the Student role (R02); the API re-enforces `grades.view_own` and reads
 * only the acting user's own record — there is no id parameter.
 */
export function MyGradesPage() {
  const { t } = useTranslation('grading')
  const { data, isPending, isError, error } = useMyGrades()
  const payload = data?.data
  const semesters = payload?.semesters ?? []

  return (
    <>
      <Typography variant="h4" component="h1" sx={{ mb: 3 }}>
        {t('myGrades.title')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('myGrades.loading')} />}
      {isError && (
        <Alert severity="error">{apiErrorMessage(error) ?? t('myGrades.loadError')}</Alert>
      )}

      {!isPending && !isError && (
        <>
          <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 2, sm: 3 }, mb: 3 }}>
            <Typography variant="overline" color="text.secondary">
              {t('myGrades.cgpaLabel')}
            </Typography>
            <Typography
              variant="h3"
              component="p"
              sx={{ ...tabularNums, fontWeight: 700, lineHeight: 1.2 }}
            >
              {gpaDisplay(payload?.cgpa, t('myGrades.dash'))}
            </Typography>
          </Paper>

          {semesters.length === 0 && (
            <Typography color="text.secondary">{t('myGrades.empty')}</Typography>
          )}

          {semesters.map((semester, index) => (
            <SemesterSection key={semester.semester?.id ?? index} semester={semester} />
          ))}
        </>
      )}
    </>
  )
}

/** One Semester's graded Courses as a card — its own GPA beside its section heading. */
function SemesterSection({ semester }: { semester: SemesterGroup }) {
  const { t, i18n } = useTranslation('grading')
  const courses = semester.courses ?? []
  const headingId = `my-grades-semester-${semester.semester?.id ?? 'x'}-heading`
  const semesterName = semester.semester ? pickLocaleName(semester.semester, i18n.language) : ''

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
        <Typography variant="body2" color="text.secondary" sx={tabularNums}>
          {t('myGrades.semesterGpa', { gpa: gpaDisplay(semester.gpa, t('myGrades.dash')) })}
        </Typography>
      </Stack>

      <TableContainer sx={{ overflowX: 'auto' }}>
        <Table size="small" aria-label={t('myGrades.tableLabel', { semester: semesterName })}>
          <TableHead>
            <TableRow>
              <TableCell>{t('myGrades.columns.course')}</TableCell>
              <TableCell align="right">{t('myGrades.columns.creditHours')}</TableCell>
              <TableCell>{t('myGrades.columns.letter')}</TableCell>
              <TableCell>{t('myGrades.columns.earned')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {courses.map((course) => (
              <CourseRowLine key={course.enrollment_id} course={course} />
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Paper>
  )
}

function CourseRowLine({ course }: { course: CourseRow }) {
  const { t, i18n } = useTranslation('grading')
  const name = course.course ? pickLocaleName(course.course, i18n.language) : ''
  const superseded = course.superseded ?? false
  const gpaPoints = course.grade?.gpa_points
  const historySummary = formatGradeHistory(readGradeHistory(course), t)
  // A superseded earlier Attempt stays visible — muted, never hidden — with an explicit label
  // rather than leaving a Student to wonder why the numbers do not add up (spec, user story 43).
  const mutedSx = superseded ? { color: 'text.secondary' } : {}
  // An Incomplete or (once converted) a Fail Withdrawal never carries a Grade (ticket 05) —
  // the letter column becomes the Course Status chip instead, and Incomplete additionally
  // shows its derived resolution deadline.
  const status = course.course_status
  const ungraded = course.grade == null && isUngradedVisibleStatus(status)

  return (
    <TableRow>
      <TableCell sx={mutedSx}>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
          <Typography variant="body2" component="span">
            <Typography component="span" sx={{ fontWeight: 600 }}>
              {course.course?.code}
            </Typography>{' '}
            {name}
          </Typography>
          {superseded && (
            <StatusChip tone="neutral" variant="outlined" label={t('myGrades.superseded')} />
          )}
        </Stack>
        {historySummary && (
          <Typography variant="caption" color="text.secondary">
            {t('myGrades.history', { grades: historySummary })}
          </Typography>
        )}
      </TableCell>
      <TableCell align="right" sx={{ ...tabularNums, ...mutedSx }}>
        {course.credit_hours}
      </TableCell>
      {ungraded ? (
        <TableCell colSpan={2}>
          <Stack spacing={0.5} sx={{ alignItems: 'flex-start' }}>
            <StatusChip {...courseStatusChip(status ?? '', t(`myGrades.courseStatus.${status}`))} />
            {status === 'Incomplete' && (
              <Typography variant="caption" color="text.secondary">
                {course.incomplete_deadline
                  ? t('myGrades.incompleteDeadline', {
                      date: deadlineDisplay(course.incomplete_deadline, i18n.language),
                    })
                  : t('myGrades.incompleteNoDeadline')}
              </Typography>
            )}
          </Stack>
        </TableCell>
      ) : (
        <>
          <TableCell>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'baseline' }}>
              <Typography
                component="span"
                variant="body2"
                sx={[
                  { fontWeight: 600 },
                  superseded
                    ? { color: 'text.secondary' }
                    : GRADE_EMPHASIS_SX[gradeEmphasisFor(gpaPoints)],
                ].flat()}
              >
                {course.grade?.letter_grade}
              </Typography>
              {gpaPoints !== undefined && (
                <Typography
                  component="span"
                  variant="caption"
                  color="text.secondary"
                  sx={tabularNums}
                >
                  {t('sheet.points', { points: gpaPoints.toFixed(2) })}
                </Typography>
              )}
            </Stack>
          </TableCell>
          <TableCell sx={mutedSx}>
            {course.earned ? t('myGrades.earned.yes') : t('myGrades.earned.no')}
          </TableCell>
        </>
      )}
    </TableRow>
  )
}
