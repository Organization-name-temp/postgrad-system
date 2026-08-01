import {
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
import { tabularNums } from '../../app/theme/fonts'
import type {
  MyGrades200Data,
  MyGrades200DataSemestersItem as SemesterGroup,
  MyGrades200DataSemestersItemCoursesItem as CourseRow,
} from '../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../shared/i18n/localeName'
import { StatusChip } from '../../shared/ui/StatusChip'
import { courseStatusChip, isUngradedVisibleStatus } from './courseStatusChip'
import { formatGradeHistory, readGradeHistory } from './gradeHistory'
import { GRADE_EMPHASIS_SX, gradeEmphasisFor } from './gradeSheetChip'

export type StudentGradesCourseRow = CourseRow & { must_retake?: boolean }
export type StudentGradesSemesterGroup = Omit<SemesterGroup, 'courses'> & {
  courses?: StudentGradesCourseRow[]
}
export type StudentGradesRecordData = Omit<MyGrades200Data, 'semesters'> & {
  semesters?: StudentGradesSemesterGroup[]
}

/** A GPA/CGPA figure formatted to two decimals, or the dash before any graded course exists. */
function gpaDisplay(value: number | null | undefined, dash: string): string {
  return typeof value === 'number' ? value.toFixed(2) : dash
}

/** A locale-formatted calendar date for an Incomplete's derived resolution deadline. */
function deadlineDisplay(iso: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(iso))
}

/**
 * The shared grade-record presentation (ticket 04 + ticket 08): one CGPA card plus the
 * per-semester course tables. My Grades and the reviewer oversight page both render this so
 * supersession, Incomplete deadlines and must-retake markers cannot drift between screens.
 */
export function StudentGradesRecord({
  data,
  emptyText,
}: {
  data?: StudentGradesRecordData
  emptyText: string
}) {
  const { t } = useTranslation('grading')
  const semesters = data?.semesters ?? []

  return (
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
          {gpaDisplay(data?.cgpa, t('myGrades.dash'))}
        </Typography>
      </Paper>

      {semesters.length === 0 && <Typography color="text.secondary">{emptyText}</Typography>}

      {semesters.map((semester, index) => (
        <SemesterSection key={semester.semester?.id ?? index} semester={semester} />
      ))}
    </>
  )
}

/** One Semester's graded Courses as a card — its own GPA beside its section heading. */
function SemesterSection({ semester }: { semester: StudentGradesSemesterGroup }) {
  const { t, i18n } = useTranslation('grading')
  const courses = semester.courses ?? []
  const headingId = `student-grades-semester-${semester.semester?.id ?? 'x'}-heading`
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

function CourseRowLine({ course }: { course: StudentGradesCourseRow }) {
  const { t, i18n } = useTranslation('grading')
  const name = course.course ? pickLocaleName(course.course, i18n.language) : ''
  const superseded = course.superseded ?? false
  const gpaPoints = course.grade?.gpa_points
  const historySummary = formatGradeHistory(readGradeHistory(course), t)
  const mutedSx = superseded ? { color: 'text.secondary' } : {}
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
          {course.must_retake && (
            <StatusChip tone="warning" variant="filled" label={t('record.mustRetake')} />
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
