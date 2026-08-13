import { Link, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { Link as RouterLink } from 'react-router-dom'
import { useAdviseeRequestsCard } from '../../../shared/api/generated/endpoints'
import { DashboardCard } from '../../../shared/ui/DashboardCard'
import { tabularNums } from '../../../app/theme/fonts'

/**
 * Advisee Requests (Dashboard Card 5, ADR-0024): the registration requests waiting on the acting
 * reviewer — the count, how many advisees they hold, and the oldest few by student name, each
 * linking into that request's review screen. Until now the queue was discoverable only by opening
 * it. Owned by the enrollment feature because Enrollment owns the data.
 *
 * Gated in the registry on `registration.approve`, which Student Affairs legitimately holds too.
 * The **scope** is the server's: an Advisor's Card counts only their own live advisees, so it follows
 * advisor reassignment; an oversight reviewer sees every advisor's, and holds no advisees of their
 * own — which is why the advisee row is omitted for them rather than showing a misleading total.
 */
export function AdviseeRequestsCard() {
  const { t } = useTranslation('enrollment')
  const { data, isPending, isError } = useAdviseeRequestsCard()
  const card = data?.data
  const pending = card?.pending_count ?? 0
  const adviseeCount = card?.advisee_count
  const requests = card?.requests ?? []

  return (
    <DashboardCard
      title={t('cards.adviseeRequests.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('cards.adviseeRequests.loadError')}
      // Nothing waiting on them reads as good news, never as a bare 0.
      isEmpty={pending === 0}
      emptyText={t('cards.adviseeRequests.empty')}
      action={{ to: '/advising', label: t('cards.adviseeRequests.action') }}
      count={
        <Stack spacing={0.5}>
          <Typography variant="h4" component="p" sx={{ ...tabularNums, fontWeight: 700 }}>
            {t('cards.adviseeRequests.pending', { count: pending })}
          </Typography>
          {/* Null, not zero, for an oversight reviewer: they hold no advisees, which is a different
              fact from holding none of them. */}
          {typeof adviseeCount === 'number' && (
            <Typography variant="body2" color="text.secondary" sx={tabularNums}>
              {t('cards.adviseeRequests.advisees', { count: adviseeCount })}
            </Typography>
          )}
        </Stack>
      }
      rows={
        <Stack spacing={0.5} component="ul" sx={{ listStyle: 'none', m: 0, p: 0 }}>
          {requests.map((request) => (
            <Typography key={request.id} component="li" variant="body2">
              <Link component={RouterLink} to={`/advising/${request.id}`} variant="body2">
                {request.student_name ?? request.university_id}
              </Link>{' '}
              <Typography component="span" variant="body2" color="text.secondary">
                {t('cards.adviseeRequests.courseCount', { count: request.course_count ?? 0 })}
              </Typography>
            </Typography>
          ))}
        </Stack>
      }
    />
  )
}
