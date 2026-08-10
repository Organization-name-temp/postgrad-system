import { Alert, CircularProgress, Paper, Stack, Typography } from '@mui/material'
import { useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  type TheStudentsWhoseGradesTheReviewerMayInspect200DataItem as ScopedStudent,
  useOneStudentsGradeRecordForAScopedReviewer,
  useTheStudentsWhoseGradesTheReviewerMayInspect,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StudentPicker } from '../../../shared/ui/StudentPicker'
import { StudentGradesRecord } from '../StudentGradesRecord'

/**
 * The scoped grade-oversight screen (ticket 08): a reviewer picks a Student and reads the
 * same academic record the Student sees on My Grades — CGPA, Semester GPAs, superseded
 * attempts, Incomplete deadlines and must-retake markers. The server scopes who appears in
 * the picker and who may be fetched by id.
 */
export function StudentGradesPage() {
  const { t, i18n } = useTranslation('grading')
  const navigate = useNavigate()
  const { studentId: studentIdParam } = useParams()
  const selectedStudentId = Number(studentIdParam)

  const studentsQuery = useTheStudentsWhoseGradesTheReviewerMayInspect()
  const students = studentsQuery.data?.data ?? []
  const student =
    Number.isFinite(selectedStudentId) && selectedStudentId > 0
      ? (students.find((row) => row.student_id === selectedStudentId) ?? null)
      : null

  const gradesQuery = useOneStudentsGradeRecordForAScopedReviewer(selectedStudentId || 0, {
    query: { enabled: Number.isFinite(selectedStudentId) && selectedStudentId > 0 },
  })

  const handleStudentChange = (next: ScopedStudent | null) => {
    void navigate(next ? `/grading/students/${next.student_id}` : '/grading/students')
  }

  return (
    <>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('oversight.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
        {t('oversight.intro')}
      </Typography>

      {studentsQuery.isPending && <CircularProgress aria-label={t('oversight.loadingStudents')} />}
      {studentsQuery.isError && (
        <Alert severity="error">
          {apiErrorMessage(studentsQuery.error) ?? t('oversight.loadStudentsError')}
        </Alert>
      )}

      {!studentsQuery.isPending && !studentsQuery.isError && (
        <Stack spacing={3}>
          <StudentPicker students={students} value={student} onChange={handleStudentChange} />

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

          {!student && <Typography color="text.secondary">{t('oversight.pickStudent')}</Typography>}

          {student && gradesQuery.isPending && (
            <CircularProgress aria-label={t('oversight.loadingRecord')} />
          )}
          {student && gradesQuery.isError && (
            <Alert severity="error">
              {apiErrorMessage(gradesQuery.error) ?? t('oversight.loadRecordError')}
            </Alert>
          )}
          {student && !gradesQuery.isPending && !gradesQuery.isError && (
            <StudentGradesRecord data={gradesQuery.data?.data} emptyText={t('oversight.empty')} />
          )}
        </Stack>
      )}
    </>
  )
}
