import { useTranslation } from 'react-i18next'
import { StatusChip, type StatusChipProps } from '../../../shared/ui/StatusChip'

const STATUS_STYLE: Record<string, Pick<StatusChipProps, 'tone' | 'variant'>> = {
  Draft: { tone: 'neutral', variant: 'outlined' },
  PendingAdvisor: { tone: 'info', variant: 'filled-soft' },
  PendingDepartmentHead: { tone: 'secondary', variant: 'filled-soft' },
  PendingCouncil: { tone: 'secondary', variant: 'filled-soft' },
  Returned: { tone: 'warning', variant: 'filled-soft' },
  Approved: { tone: 'success', variant: 'filled' },
  Rejected: { tone: 'error', variant: 'filled' },
  Abandoned: { tone: 'neutral', variant: 'outlined' },
}

export function WaiverStatusChip({ status }: { status?: string }) {
  const { t } = useTranslation('enrollment')
  const resolved = status ?? 'Draft'

  return (
    <StatusChip
      {...(STATUS_STYLE[resolved] ?? STATUS_STYLE.Draft)}
      label={t(`waivers.status.${resolved}`)}
    />
  )
}
