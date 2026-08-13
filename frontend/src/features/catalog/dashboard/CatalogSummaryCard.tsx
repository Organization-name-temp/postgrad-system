import { Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useCatalogSummaryCard } from '../../../shared/api/generated/endpoints'
import { DashboardCard } from '../../../shared/ui/DashboardCard'
import { tabularNums } from '../../../app/theme/fonts'

/**
 * Catalog Summary (Dashboard Card 11, ADR-0024): how many Programs and Courses are active,
 * linking into the catalog — the answer to "is the catalog populated?" without a navigation.
 * Owned by the catalog feature because Catalog owns the data; the app shell only arranges it.
 *
 * Gated in the registry on `catalog.manage` and a member of the **administration Card set**: like
 * the Semester Card it is about how the system is *configured*, so an administrator sees it, and
 * so do the Department Head / Dean holders the §3.2 matrix gives that Permission to.
 *
 * A Card, not a Report: fixed and summary, where Phase 7's Enrollment Summary report is
 * filterable and parameterised (spec §Glossary).
 */
export function CatalogSummaryCard() {
  const { t } = useTranslation('catalog')
  const { data, isPending, isError } = useCatalogSummaryCard()
  const card = data?.data
  const programs = card?.active_program_count ?? 0
  const courses = card?.active_course_count ?? 0

  return (
    <DashboardCard
      title={t('cards.summary.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('cards.summary.loadError')}
      // An unpopulated catalog is what this Card exists to reveal, so it says so in words
      // rather than showing the pair of bare zeros the empty-state rule forbids.
      isEmpty={programs === 0 && courses === 0}
      emptyText={t('cards.summary.empty')}
      action={{ to: '/catalog', label: t('cards.summary.action') }}
      count={
        <Stack direction="row" spacing={4} sx={{ flexWrap: 'wrap' }}>
          <Figure label={t('cards.summary.programs')} value={programs} />
          <Figure label={t('cards.summary.courses')} value={courses} />
        </Stack>
      }
    />
  )
}

/** One labelled count — the label above, the number given the weight below it. */
function Figure({ label, value }: { label: string; value: number }) {
  return (
    <Stack spacing={0}>
      <Typography variant="overline" color="text.secondary">
        {label}
      </Typography>
      <Typography variant="h4" component="p" sx={{ ...tabularNums, fontWeight: 700 }}>
        {value}
      </Typography>
    </Stack>
  )
}
