import { Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useMyApplicationCard } from '../../../shared/api/generated/endpoints'
import { formatDate } from '../../../shared/datetime'
import { DashboardCard } from '../../../shared/ui/DashboardCard'
import { ApplicationStatusChip } from '../ApplicationStatusChip'
import { toApplicationStatus } from '../applicationStatus'

/**
 * My Application (Dashboard Card 1, ADR-0024): where the Applicant's Application stands — the
 * current status as a `<StatusChip>`, what happens next or what they must do, and a link into the
 * full status timeline. Today the only way to learn any of this is to open the application itself.
 * Owned by the admissions feature because Admissions owns the data; the app shell only arranges it.
 *
 * A **persona** Card, gated in the registry on the Applicant role (R01) rather than on
 * `application.view_status`: an administrator inherits that Permission (ADR-0008) and holds no
 * Application at all, so a Permission gate would render a broken Card — the existing convention the
 * persona nav items have followed since Phase 2.
 *
 * The next-action copy is picked *here*, from the status the server sent as an enum value. That is
 * the bilingual rule, not a shortcut: the "next action" is a rendering of a state the domain already
 * has, so the server never sends a sentence and the Arabic is a first-class translation rather than
 * a re-layout of English.
 */
export function MyApplicationCard() {
  const { t, i18n } = useTranslation('admissions')
  const { data, isPending, isError, refetch } = useMyApplicationCard()
  const application = data?.data ?? null
  const status = toApplicationStatus(application?.status)

  return (
    <DashboardCard
      title={t('cards.myApplication.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('cards.myApplication.loadError')}
      onRetry={() => void refetch()}
      // No Application yet — the state between creating an account and starting the first draft. A
      // legitimate state, so it reads as an invitation rather than as a failure.
      isEmpty={application === null}
      emptyText={t('cards.myApplication.empty')}
      action={{ to: '/apply', label: t('cards.myApplication.action') }}
      count={<ApplicationStatusChip status={application?.status} />}
      rows={
        <Stack spacing={0.5}>
          {/* What happens next, keyed on the status: whether the university or the applicant is
              holding things up is the one question the Card exists to answer. */}
          {status && (
            <Typography variant="body2">{t(`cards.myApplication.next.${status}`)}</Typography>
          )}
          {application?.status_entered_at && (
            <Typography variant="body2" color="text.secondary">
              {t('cards.myApplication.since', {
                date: formatDate(application.status_entered_at, i18n.language),
              })}
            </Typography>
          )}
        </Stack>
      }
    />
  )
}
