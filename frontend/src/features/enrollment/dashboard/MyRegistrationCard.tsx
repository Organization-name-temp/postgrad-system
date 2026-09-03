import { Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useMyRegistrationCard } from '../../../shared/api/generated/endpoints'
import { formatDate } from '../../../shared/datetime'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { DashboardCard } from '../../../shared/ui/DashboardCard'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { tabularNums } from '../../../app/theme/fonts'

/**
 * The periods in which a Student composes a **cart** — a registration or a late-add request goes
 * through the advisor, so the Card sends them to the registration screen. Every other period
 * (add/drop's direct changes, withdrawal, and a closed term) belongs to My Schedule. This is the
 * ticket's "link to the action that is actually available", derived from the period the server
 * reported rather than from a fixed destination.
 */
const CART_PERIODS = ['registration', 'late_add']

/**
 * My Registration (Dashboard Card 4, ADR-0024): the Student's current-semester registration at a
 * glance — the credit hours they carry with the 6–12 bounds **alongside** them so the number is
 * anchored, which period is open, and when it closes. Owned by the enrollment feature because
 * Enrollment owns the data; the app shell only arranges it.
 *
 * A **persona** Card, gated in the registry on the Student role (R02) rather than on
 * `courses.register_self`: an administrator inherits that Permission (ADR-0008) but holds no Student
 * anchor, so gating on the Permission would render a broken Card — the existing convention the
 * persona nav items have followed since Phase 3. The server refuses the endpoint for the same
 * reason.
 *
 * Renders bilingually from typed domain values: the Semester arrives as id/term/year/names and the
 * period as a key, so both labels are picked here rather than sent pre-rendered.
 */
export function MyRegistrationCard() {
  const { t, i18n } = useTranslation('enrollment')
  const { data, isPending, isError, refetch } = useMyRegistrationCard()
  const card = data?.data
  const semester = card?.semester
  const period = card?.current_period
  const creditHours = card?.credit_hours ?? 0
  const registered = (card?.enrollment_count ?? 0) > 0

  const to = period && CART_PERIODS.includes(period) ? '/registration' : '/schedule'

  return (
    <DashboardCard
      title={t('cards.myRegistration.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('cards.myRegistration.loadError')}
      onRetry={() => void refetch()}
      // No term running and none open for registration: there is nothing to register into yet, and
      // saying so beats showing a 0 that looks like a mistake.
      isEmpty={!semester}
      emptyText={t('cards.myRegistration.empty')}
      action={{
        to,
        label:
          to === '/registration'
            ? t('cards.myRegistration.register')
            : t('cards.myRegistration.schedule'),
      }}
      count={
        <Stack spacing={0.5}>
          {/* Nothing registered yet is said in words, because "0 credit hours" is exactly the bare
              zero the empty-state rule exists to prevent — the same treatment My Academic Record
              gives its earned hours before the first finalized Grade. The period and its deadline
              stay visible below, since they are what the Student needs in order to act. */}
          {registered ? (
            <Typography variant="h4" component="p" sx={{ ...tabularNums, fontWeight: 700 }}>
              {t('cards.myRegistration.creditHours', { count: creditHours })}
            </Typography>
          ) : (
            <Typography variant="body1" component="p">
              {t('cards.myRegistration.nothingRegistered')}
            </Typography>
          )}
          {/* The bounds travel with the number, so "9" is legible as a load rather than left to
              be judged against a limit the Student is expected to remember. */}
          {registered && (
            <Typography variant="body2" color="text.secondary" sx={tabularNums}>
              {t('cards.myRegistration.bounds', {
                min: card?.min_credit_hours ?? 0,
                max: card?.max_credit_hours ?? 0,
              })}
            </Typography>
          )}
        </Stack>
      }
      rows={
        <Stack spacing={1}>
          {semester && (
            <Typography variant="body2" color="text.secondary">
              {pickLocaleName(semester, i18n.language)}
            </Typography>
          )}
          {period && (
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
              <StatusChip
                tone={period === 'closed' ? 'neutral' : 'info'}
                variant={period === 'closed' ? 'outlined' : 'filled-soft'}
                label={t(`cards.myRegistration.periods.${period}`)}
              />
              {card?.period_ends_at && (
                <Typography variant="body2" color="text.secondary">
                  {t('cards.myRegistration.closes', {
                    date: formatDate(card.period_ends_at, i18n.language),
                  })}
                </Typography>
              )}
            </Stack>
          )}
        </Stack>
      }
    />
  )
}
