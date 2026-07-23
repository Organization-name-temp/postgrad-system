import { Alert, CircularProgress, Paper, Stack, Typography } from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getTheActingStudentsRegistrationRequestsForTheOpenSemesterQueryKey,
  useListTheActingStudentsEligibleCoursesForTheOpenSemester,
  useTheActingStudentsRegistrationRequestsForTheOpenSemester,
} from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { useAuth } from '../../auth/useAuth'
import { CartBuilder } from './CartBuilder'
import { RequestView } from './RequestView'

/**
 * The Student's course-registration screen (features/enrollment). It shows either the
 * cart builder — the eligible list (grouped by Enrollment Classification) plus a live,
 * client-validated cart (ADR-0014) that submits a RegistrationRequest — or, once a request
 * is Pending, the request view with its status and per-line detail (ticket 05). A Rejected
 * request is shown above a fresh builder, so a rejection is recoverable while the window is
 * open. The route is persona-guarded to R02 and the API re-enforces `courses.register_self`.
 */
export function RegistrationPage() {
  const { t, i18n } = useTranslation('enrollment')
  const { user } = useAuth()
  const queryClient = useQueryClient()

  const eligibleQuery = useListTheActingStudentsEligibleCoursesForTheOpenSemester()
  const requestsQuery = useTheActingStudentsRegistrationRequestsForTheOpenSemester()

  const isLoading = eligibleQuery.isPending || requestsQuery.isPending
  const isError = eligibleQuery.isError || requestsQuery.isError

  const semester = requestsQuery.data?.data?.semester ?? eligibleQuery.data?.data?.semester
  const courses = eligibleQuery.data?.data?.courses ?? []
  const requests = requestsQuery.data?.data?.requests ?? []
  const pending = requests.find((request) => request.status === 'Pending')
  const priorRequests = requests.filter((request) => request.status !== 'Pending')

  // Mirror the server's first-semester CH waiver: the open semester is the student's
  // enrollment term (matched by natural key — the display name, ADR-0018).
  const firstSemester = Boolean(
    user?.student?.enrollment_term &&
    semester?.name_en &&
    user.student.enrollment_term === semester.name_en,
  )

  const onSubmitted = () =>
    queryClient.invalidateQueries({
      queryKey: getTheActingStudentsRegistrationRequestsForTheOpenSemesterQueryKey(),
    })

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

      {isLoading && <CircularProgress aria-label={t('registration.loading')} />}
      {isError && <Alert severity="error">{t('registration.loadError')}</Alert>}

      {!isLoading && !isError && !semester && (
        <Alert severity="info">{t('registration.notOpen')}</Alert>
      )}

      {!isLoading && !isError && semester && pending && <RequestView request={pending} />}

      {!isLoading && !isError && semester && !pending && (
        <>
          {priorRequests.map((request) => (
            <RequestView key={request.id} request={request} />
          ))}

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
              {priorRequests.length > 0 && (
                <Typography variant="body2" color="text.secondary">
                  {t('request.rejectedHelp')}
                </Typography>
              )}
            </Stack>
          </Paper>

          {courses.length === 0 ? (
            <Typography color="text.secondary">{t('registration.empty')}</Typography>
          ) : (
            <CartBuilder
              courses={courses}
              firstSemester={firstSemester}
              onSubmitted={onSubmitted}
            />
          )}
        </>
      )}
    </>
  )
}
