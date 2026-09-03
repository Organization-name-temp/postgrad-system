import { Link, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { Link as RouterLink } from 'react-router-dom'
import { useMyGradeSheetsCard } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { DashboardCard } from '../../../shared/ui/DashboardCard'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { tabularNums } from '../../../app/theme/fonts'

/**
 * My Grade Sheets (Dashboard Card 6, ADR-0024): the acting user's teaching load for their current
 * term and what it owes — how many Grade Sheets are **not yet Finalized**, and a flag on each
 * Offering whose Grade-Submission Deadline has passed with nothing finalized. Each row links into
 * that Offering's Sheet. Owned by the grading feature because Grading owns the data; the app shell
 * only arranges it.
 *
 * The overdue flag is a **nudge, not a gate** — past the deadline an Offering with no Finalized
 * Sheet is flagged and nothing is blocked or changed. This Card is a second surface for that
 * existing flag and must not acquire an opinion the domain does not have, so it renders a chip and
 * offers exactly the same link as an in-time Offering.
 *
 * Gated in the registry on `grades.enter`, which Student Affairs holds too (§3.2) and legitimately
 * sees for central entry: the server returns every Offering for them, exactly as their Grade Sheets
 * screen already does, so the Card is meaningful rather than empty.
 */
export function MyGradeSheetsCard() {
  const { t, i18n } = useTranslation('grading')
  const { data, isPending, isError, refetch } = useMyGradeSheetsCard()
  const card = data?.data
  const semester = card?.semester
  const offerings = card?.offerings ?? []
  const unfinalized = card?.unfinalized_count ?? 0
  const overdue = card?.overdue_count ?? 0

  return (
    <DashboardCard
      title={t('cards.gradeSheets.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('cards.gradeSheets.loadError')}
      onRetry={() => void refetch()}
      // Nothing to grade this term: not a zero to stare at, but the honest answer that no term of
      // theirs is currently owed marks.
      isEmpty={!semester || offerings.length === 0}
      emptyText={t('cards.gradeSheets.empty')}
      action={{ to: '/grading', label: t('cards.gradeSheets.action') }}
      count={
        <Stack spacing={0.5}>
          <Typography variant="h4" component="p" sx={{ ...tabularNums, fontWeight: 700 }}>
            {t('cards.gradeSheets.unfinalized', { count: unfinalized })}
          </Typography>
          {semester && (
            <Typography variant="body2" color="text.secondary">
              {t('cards.gradeSheets.thisTerm', {
                semester: pickLocaleName(semester, i18n.language),
                count: card?.offering_count ?? 0,
              })}
            </Typography>
          )}
          {overdue > 0 && (
            <StatusChip
              tone="warning"
              variant="filled-soft"
              label={t('cards.gradeSheets.overdue', { count: overdue })}
            />
          )}
        </Stack>
      }
      rows={
        <Stack spacing={0.5} component="ul" sx={{ listStyle: 'none', m: 0, p: 0 }}>
          {offerings.map((offering) => (
            <Stack
              key={offering.offering_id}
              component="li"
              direction="row"
              spacing={1}
              sx={{ alignItems: 'center', flexWrap: 'wrap' }}
            >
              {/* Each row links into that Offering's own Sheet, so the Card is a way in rather
                  than a list to read and then navigate away from. */}
              <Link
                component={RouterLink}
                to={`/grading/offerings/${offering.offering_id}`}
                variant="body2"
              >
                {offering.course ? (
                  <>
                    <span dir="ltr" style={{ unicodeBidi: 'isolate' }}>
                      {offering.course.code}
                    </span>{' '}
                    · {pickLocaleName(offering.course, i18n.language)}
                  </>
                ) : (
                  t('cards.gradeSheets.unknownCourse')
                )}
              </Link>
              {offering.is_overdue && (
                <StatusChip
                  tone="warning"
                  variant="outlined"
                  label={t('cards.gradeSheets.pastDeadline')}
                />
              )}
            </Stack>
          ))}
        </Stack>
      }
    />
  )
}
