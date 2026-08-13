import { Link, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { Link as RouterLink } from 'react-router-dom'
import { useSheetsAwaitingReviewCard } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { DashboardCard } from '../../../shared/ui/DashboardCard'
import { tabularNums } from '../../../app/theme/fonts'

/**
 * Sheets Awaiting Review (Dashboard Card 7, ADR-0024): the Department Head's review queue at a
 * glance — how many Submitted Grade Sheets are waiting on them and which Offerings the oldest few
 * belong to, each linking into that Sheet's review screen. An Instructor blocked on a Head becomes
 * visible to that Head on sign-in. Owned by the grading feature because Grading owns the data.
 *
 * Gated in the registry on `grades.finalize`, which Instructors hold too (§3.2): the Permission
 * opens the Card and the **Department assignment** fills it, so an Instructor who heads no
 * Department sees the empty state rather than a broken Card — including, for a Head assigned
 * university-wide, the department-less Preparatory Sheets a Department-scoped Head never sees.
 */
export function SheetsAwaitingReviewCard() {
  const { t, i18n } = useTranslation('grading')
  const { data, isPending, isError } = useSheetsAwaitingReviewCard()
  const card = data?.data
  const pending = card?.pending_count ?? 0
  const sheets = card?.sheets ?? []

  return (
    <DashboardCard
      title={t('cards.sheetsAwaitingReview.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('cards.sheetsAwaitingReview.loadError')}
      // Nothing waiting reads as good news, never as a bare 0.
      isEmpty={pending === 0}
      emptyText={t('cards.sheetsAwaitingReview.empty')}
      action={{ to: '/grading/review', label: t('cards.sheetsAwaitingReview.action') }}
      count={
        <Typography variant="h4" component="p" sx={{ ...tabularNums, fontWeight: 700 }}>
          {t('cards.sheetsAwaitingReview.pending', { count: pending })}
        </Typography>
      }
      rows={
        <Stack spacing={0.5} component="ul" sx={{ listStyle: 'none', m: 0, p: 0 }}>
          {sheets.map((sheet) => (
            <Typography key={sheet.id} component="li" variant="body2">
              <Link component={RouterLink} to={`/grading/review/${sheet.id}`} variant="body2">
                {sheet.course
                  ? `${sheet.course.code} · ${pickLocaleName(sheet.course, i18n.language)}`
                  : t('cards.sheetsAwaitingReview.unknownCourse')}
              </Link>
            </Typography>
          ))}
        </Stack>
      }
    />
  )
}
