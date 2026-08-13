import { Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useSemesterAndOpenWindowsCard } from '../../../shared/api/generated/endpoints'
import { formatDate } from '../../../shared/datetime'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { DashboardCard } from '../../../shared/ui/DashboardCard'
import { StatusChip } from '../../../shared/ui/StatusChip'

/**
 * Semester & Open Windows (Dashboard Card 10, ADR-0024): which Semester is live, which of its
 * period windows are open right now, and the next date due — linking into Semester management.
 * Owned by the admin feature because the Semester management screen is, though Calendar owns the
 * data and serves the endpoint; the app shell only arranges it.
 *
 * Gated in the registry on `calendar.manage` and a member of the **administration Card set**: it
 * is a Card about how the system is *configured*, so an administrator sees it, and so do the
 * Student Affairs / Dean holders the §3.2 matrix gives that Permission to.
 *
 * Renders bilingually from typed domain values: the Semester arrives as id/term/year/names and
 * every window as a key, so the label is picked here rather than sent pre-rendered.
 */
export function SemesterWindowsCard() {
  const { t, i18n } = useTranslation('admin')
  const { data, isPending, isError } = useSemesterAndOpenWindowsCard()
  const card = data?.data
  const semester = card?.semester
  const openWindows = card?.open_windows ?? []
  const nextDeadline = card?.next_deadline

  return (
    <DashboardCard
      title={t('cards.semesterWindows.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('cards.semesterWindows.loadError')}
      // No Semester running and none open for registration: a real configuration state, and
      // "no windows are open" is the honest reading of it rather than an error or a blank.
      isEmpty={!semester}
      emptyText={t('cards.semesterWindows.empty')}
      action={{ to: '/admin/semesters', label: t('cards.semesterWindows.action') }}
      count={
        semester && (
          <Typography variant="h5" component="p" sx={{ fontWeight: 700 }}>
            {pickLocaleName(semester, i18n.language)}
          </Typography>
        )
      }
      rows={
        <Stack spacing={1}>
          <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', gap: 1 }}>
            {/* Every open window as its own chip — plural on purpose: registration may still be
                open past the semester start, so the two overlap. */}
            {openWindows.length > 0 ? (
              openWindows.map((key) => (
                <StatusChip
                  key={key}
                  tone="success"
                  variant="filled-soft"
                  label={t(`cards.semesterWindows.windows.${key}`)}
                />
              ))
            ) : (
              <StatusChip
                tone="neutral"
                variant="outlined"
                label={t('cards.semesterWindows.noOpenWindows')}
              />
            )}
          </Stack>

          <Typography variant="body2" color="text.secondary">
            {nextDeadline
              ? t('cards.semesterWindows.nextDeadline', {
                  window: t(`cards.semesterWindows.deadlines.${nextDeadline.window}`),
                  date: formatDate(nextDeadline.at, i18n.language),
                })
              : t('cards.semesterWindows.noNextDeadline')}
          </Typography>
        </Stack>
      }
    />
  )
}
