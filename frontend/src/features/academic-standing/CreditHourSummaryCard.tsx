import { Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useMyCreditHourSummaryDashboardCard } from '../../shared/api/generated/endpoints'
import { DashboardCard } from '../../shared/ui/DashboardCard'
import { DashboardFigure } from '../../shared/ui/DashboardFigure'

export function CreditHourSummaryCard() {
  const { t } = useTranslation('academicStanding')
  const { data, isPending, isError, refetch } = useMyCreditHourSummaryDashboardCard()
  const summary = data?.data

  return (
    <DashboardCard
      title={t('card.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('card.loadError')}
      onRetry={() => void refetch()}
      action={{ to: '/academic-standing', label: t('card.action') }}
      count={
        <Stack direction="row" spacing={4}>
          <DashboardFigure label={t('card.satisfied')} value={summary?.satisfied_ch ?? 0} />
          <DashboardFigure label={t('card.remaining')} value={summary?.remaining_ch ?? 0} />
        </Stack>
      }
      rows={
        <Typography variant="body2" color="text.secondary">
          {t('card.requirement', { count: summary?.adjusted_required_ch ?? 0 })}
        </Typography>
      }
    />
  )
}
