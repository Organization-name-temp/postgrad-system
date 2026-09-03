import { Link, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { Link as RouterLink } from 'react-router-dom'
import { useScreeningQueueCard } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { DashboardCard } from '../../../shared/ui/DashboardCard'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { tabularNums } from '../../../app/theme/fonts'

/**
 * Screening Queue (Dashboard Card 2, ADR-0024): how much screening work is waiting — the count of
 * Applications pending screening, **how many of those are overdue**, and the three oldest by name
 * and age, each linking into the screening queue. Owned by the admissions feature because Admissions
 * owns the data; the app shell only arranges it.
 *
 * This Card closes **SRS FR-ADM-005.5** — *"the system SHALL display pending application count and
 * aging on the Student Affairs dashboard"* — unbuilt since Phase 2 because there was no dashboard to
 * display it on.
 *
 * Overdue is a **count and a visual distinction**, nothing more: the server changes nothing and
 * blocks nothing by it, exactly as the existing flag-and-notify command does not, so this Card
 * offers an overdue Application precisely the same link as any other. The threshold arrives as
 * `sla_days` so the copy can say *why* something is overdue without the SPA hard-coding the
 * university's SLA.
 */
export function ScreeningQueueCard() {
  const { t, i18n } = useTranslation('admissions')
  const { data, isPending, isError, refetch } = useScreeningQueueCard()
  const card = data?.data
  const pending = card?.pending_count ?? 0
  const overdue = card?.overdue_count ?? 0
  const applications = card?.applications ?? []

  return (
    <DashboardCard
      title={t('cards.screeningQueue.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('cards.screeningQueue.loadError')}
      onRetry={() => void refetch()}
      // An empty queue reads as good news, never as a bare 0.
      isEmpty={pending === 0}
      emptyText={t('cards.screeningQueue.empty')}
      action={{ to: '/screening', label: t('cards.screeningQueue.action') }}
      count={
        <Stack spacing={0.5}>
          <Typography variant="h4" component="p" sx={{ ...tabularNums, fontWeight: 700 }}>
            {t('cards.screeningQueue.pending', { count: pending })}
          </Typography>
          {overdue > 0 && (
            <StatusChip
              tone="warning"
              variant="filled-soft"
              label={t('cards.screeningQueue.overdue', {
                count: overdue,
                days: card?.sla_days ?? 0,
              })}
            />
          )}
        </Stack>
      }
      rows={
        <Stack spacing={0.5} component="ul" sx={{ listStyle: 'none', m: 0, p: 0 }}>
          {applications.map((application) => (
            <Stack
              key={application.id}
              component="li"
              direction="row"
              spacing={1}
              sx={{ alignItems: 'center', flexWrap: 'wrap' }}
            >
              <Link
                component={RouterLink}
                to={`/screening/${application.id}`}
                variant="body2"
                sx={{ fontWeight: application.is_overdue ? 600 : undefined }}
              >
                {pickLocaleName(application, i18n.language)}
              </Link>
              <Typography
                variant="body2"
                // Never colour alone (WCAG 1.4.1): the age text says how long it has waited and the
                // overdue rows carry their own word for it.
                sx={(theme) => ({
                  ...tabularNums,
                  color: application.is_overdue
                    ? theme.palette.mode === 'light'
                      ? theme.palette.warning.dark
                      : theme.palette.warning.main
                    : theme.palette.text.secondary,
                })}
              >
                {application.is_overdue
                  ? t('cards.screeningQueue.rowOverdue', { count: application.age_days ?? 0 })
                  : t('cards.screeningQueue.rowWaiting', { count: application.age_days ?? 0 })}
              </Typography>
            </Stack>
          ))}
        </Stack>
      }
    />
  )
}
