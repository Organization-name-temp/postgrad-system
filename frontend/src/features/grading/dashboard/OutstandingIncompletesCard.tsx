import { Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useOutstandingIncompletesCard } from '../../../shared/api/generated/endpoints'
import { formatDate } from '../../../shared/datetime'
import { DashboardCard } from '../../../shared/ui/DashboardCard'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { tabularNums } from '../../../app/theme/fonts'

/**
 * Outstanding Incompletes (Dashboard Card 9, ADR-0024): how many Incompletes the acting Instructor
 * has left waiting on their mark, and when the nearest one is due — linking into the resolution
 * screen. Owned by the grading feature because Grading owns the data; the app shell only arranges
 * it.
 *
 * This Card earns its place by preventing real harm: an Incomplete left unresolved past its
 * deadline converts to a **Fail Withdrawal**, and that conversion is the single state-changing
 * scheduled command in the system. Surfacing the nearest deadline on sign-in is the Instructor's
 * chance to resolve it first.
 *
 * The deadline is derived server-side and never stored, so a corrected Semester start moves it and
 * this Card with it. An Incomplete whose following Semester does not exist in the Calendar yet has
 * **no computable deadline**, and the Card says so rather than inventing one — the case the
 * conversion command already handles explicitly, and an admin's omission that must not hide an
 * Instructor's work from them.
 *
 * Gated in the registry on `grades.enter`, the same permission the resolution screen sits behind.
 */
export function OutstandingIncompletesCard() {
  const { t, i18n } = useTranslation('grading')
  const { data, isPending, isError } = useOutstandingIncompletesCard()
  const card = data?.data
  const outstanding = card?.outstanding_count ?? 0
  const withoutDeadline = card?.without_deadline_count ?? 0
  const nearest = card?.nearest_deadline

  return (
    <DashboardCard
      title={t('cards.outstandingIncompletes.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('cards.outstandingIncompletes.loadError')}
      // Nothing outstanding reads as good news, never as a bare 0.
      isEmpty={outstanding === 0}
      emptyText={t('cards.outstandingIncompletes.empty')}
      action={{ to: '/grading/incompletes', label: t('cards.outstandingIncompletes.action') }}
      count={
        <Typography variant="h4" component="p" sx={{ ...tabularNums, fontWeight: 700 }}>
          {t('cards.outstandingIncompletes.outstanding', { count: outstanding })}
        </Typography>
      }
      rows={
        <Stack spacing={1} sx={{ alignItems: 'flex-start' }}>
          <Typography variant="body2" color="text.secondary">
            {nearest
              ? t('cards.outstandingIncompletes.nearestDeadline', {
                  date: formatDate(nearest, i18n.language),
                })
              : t('cards.outstandingIncompletes.noDeadlineAtAll')}
          </Typography>

          {/* Never silently folded into the count: an Incomplete the Calendar cannot yet date is
              still the Instructor's to resolve, and it is the one the conversion command will
              reach first once the missing Semester is added. */}
          {withoutDeadline > 0 && (
            <StatusChip
              tone="neutral"
              variant="outlined"
              label={t('cards.outstandingIncompletes.withoutDeadline', { count: withoutDeadline })}
            />
          )}
        </Stack>
      }
    />
  )
}
