import { Alert, CircularProgress, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useMyGrades } from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { StudentGradesRecord } from '../StudentGradesRecord'

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
        <StudentGradesRecord data={payload} emptyText={t('myGrades.empty')} />
      )}
    </>
  )
}
