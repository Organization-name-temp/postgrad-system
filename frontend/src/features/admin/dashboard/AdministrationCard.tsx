import { Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useAdministrationCard } from '../../../shared/api/generated/endpoints'
import { formatDate } from '../../../shared/datetime'
import { DashboardCard } from '../../../shared/ui/DashboardCard'
import { DashboardFigure } from '../../../shared/ui/DashboardFigure'

/**
 * Administration (Dashboard Card 12, ADR-0024): the shape of the system at a glance — how many
 * Users exist and how many Role assignments they hold between them — over the most recent Audit Log
 * activity, linking into the administration surfaces. Owned by the admin feature; the app shell only
 * arranges it.
 *
 * This is the phase's one deliberate exception to the composition rule. R09 and R10 hold **every**
 * Permission (ADR-0008), so a permission-union Dashboard would stack every Card in the system onto
 * an administrator's page; holding every Permission is an authorization fact, not a description of
 * an administrator's job. They get an explicit set instead — this Card plus the two configuration
 * Cards — so it is registered as `adminSet` **and** gated on `ADMIN_ROLES`, which is what refuses it
 * to everyone else. The server refuses it on the same Role, through the `admin` middleware every
 * administration route has used since Phase 2.
 *
 * It is a **Card, not the Phase 7 Audit Log Report**: fixed, scoped and summary, with no filter, no
 * date range and no export. Each entry arrives as its action *key* and is labelled here, bilingually,
 * exactly as Notifications renders its types — an action the SPA has no label for falls back to the
 * key itself rather than to a blank, so a new audit action added by a later phase is legible on this
 * Card before anyone translates it.
 */
export function AdministrationCard() {
  const { t, i18n } = useTranslation('admin')
  const { data, isPending, isError, refetch } = useAdministrationCard()
  const card = data?.data
  const users = card?.user_count ?? 0
  const roleAssignments = card?.role_assignment_count ?? 0
  const activity = card?.recent_activity ?? []

  return (
    <DashboardCard
      title={t('cards.administration.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('cards.administration.loadError')}
      onRetry={() => void refetch()}
      action={{ to: '/admin/assignments', label: t('cards.administration.action') }}
      count={
        <Stack direction="row" spacing={4} sx={{ flexWrap: 'wrap' }}>
          <DashboardFigure label={t('cards.administration.users')} value={users} />
          <DashboardFigure
            label={t('cards.administration.roleAssignments')}
            value={roleAssignments}
          />
        </Stack>
      }
      rows={
        <Stack spacing={0.5} component="ul" sx={{ listStyle: 'none', m: 0, p: 0 }}>
          <Typography variant="overline" color="text.secondary" component="li">
            {t('cards.administration.recentActivity')}
          </Typography>

          {/* An empty log is a legitimate state — a fresh installation has recorded nothing yet — so
              it reads as words rather than as an error. The Card itself is never empty: the counts
              beside this list are always there to show, which is why the shared empty-state slot
              would be the wrong place to say it. */}
          {activity.length === 0 ? (
            <Typography variant="body2" color="text.secondary" component="li">
              {t('cards.administration.noActivity')}
            </Typography>
          ) : (
            activity.map((entry) => (
              <Typography key={entry.id} variant="body2" color="text.secondary" component="li">
                {t('cards.administration.entry', {
                  action: t(`cards.administration.actions.${entry.action}`, {
                    defaultValue: entry.action,
                  }),
                  actor: entry.actor_name ?? t('cards.administration.noActor'),
                  date: formatDate(entry.at, i18n.language),
                })}
              </Typography>
            ))
          )}
        </Stack>
      }
    />
  )
}
