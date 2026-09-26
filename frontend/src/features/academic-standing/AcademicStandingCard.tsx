import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import ErrorIcon from '@mui/icons-material/Error'
import HourglassEmptyIcon from '@mui/icons-material/HourglassEmpty'
import WarningAmberIcon from '@mui/icons-material/WarningAmber'
import { Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useMyAcademicStandingDashboardCard } from '../../shared/api/generated/endpoints'
import { DashboardCard } from '../../shared/ui/DashboardCard'
import { StatusChip } from '../../shared/ui/StatusChip'

export function standingPresentation(standing: string | undefined) {
  switch (standing) {
    case 'GoodStanding':
      return { tone: 'success' as const, icon: <CheckCircleIcon /> }
    case 'AcademicWarning':
      return { tone: 'warning' as const, icon: <WarningAmberIcon /> }
    case 'AcademicProbation':
    case 'TerminationPending':
      return { tone: 'error' as const, icon: <ErrorIcon /> }
    default:
      return { tone: 'neutral' as const, icon: <HourglassEmptyIcon /> }
  }
}

export function AcademicStandingCard() {
  const { t } = useTranslation('academicStanding')
  const { data, isPending, isError, refetch } = useMyAcademicStandingDashboardCard()
  const standing = data?.data?.standing
  const presentation = standingPresentation(standing)

  return (
    <DashboardCard
      title={t('standing.cardTitle')}
      isPending={isPending}
      isError={isError}
      errorText={t('standing.loadError')}
      onRetry={() => void refetch()}
      action={{ to: '/academic-standing', label: t('standing.action') }}
      count={
        <StatusChip
          tone={presentation.tone}
          variant="filled-soft"
          icon={presentation.icon}
          label={t(`standing.values.${standing ?? 'NotYetAssessed'}`)}
        />
      }
      rows={
        <Typography variant="body2" color="text.secondary">
          {t(`standing.explanations.${data?.data?.explanation ?? 'no_finalized_cgpa_grade'}`)}
        </Typography>
      }
    />
  )
}
