import { Alert, CircularProgress, Paper, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useListTheActingStudentsEligibleCoursesForTheOpenSemester } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { ClassificationSection } from './ClassificationSection'
import { groupByClassification } from './classification'

/**
 * The Student's course-registration screen (features/enrollment, ticket 04). It shows
 * the courses the acting Student may register for in the currently-open Semester,
 * grouped by their Enrollment Classification (Preparatory / Core / Internal Elective /
 * External Elective / Research Course). Read-only in this ticket — the cart and
 * submission arrive in ticket 05. The route is persona-guarded to R02 and the API
 * re-enforces `courses.register_self` on every request (ADR-0008).
 */
export function RegistrationPage() {
  const { t, i18n } = useTranslation('enrollment')
  const { data, isPending, isError } = useListTheActingStudentsEligibleCoursesForTheOpenSemester()

  const semester = data?.data?.semester
  const courses = data?.data?.courses ?? []
  const groups = groupByClassification(courses)

  const semesterName = semester ? pickLocaleName(semester, i18n.language) : ''
  const closesAt = semester?.registration_closes_at
  const closesLabel = closesAt
    ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'long' }).format(new Date(closesAt))
    : ''

  return (
    <>
      <Typography variant="h4" component="h1" sx={{ mb: 3 }}>
        {t('registration.title')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('registration.loading')} />}
      {isError && <Alert severity="error">{t('registration.loadError')}</Alert>}

      {!isPending && !isError && !semester && (
        <Alert severity="info">{t('registration.notOpen')}</Alert>
      )}

      {!isPending && !isError && semester && (
        <>
          <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 2, sm: 3 }, mb: 3 }}>
            <Stack spacing={0.5}>
              <Typography variant="h6" component="p">
                {t('registration.forSemester', { semester: semesterName })}
              </Typography>
              {closesLabel && (
                <Typography variant="body2" color="text.secondary">
                  {t('registration.openUntil', { date: closesLabel })}
                </Typography>
              )}
            </Stack>
          </Paper>

          {groups.length === 0 ? (
            <Typography color="text.secondary">{t('registration.empty')}</Typography>
          ) : (
            groups.map((group) => (
              <ClassificationSection key={group.classification} group={group} />
            ))
          )}
        </>
      )}
    </>
  )
}
