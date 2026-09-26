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

export function TransferStatusChip({ status }: { status?: string }) {
  const { t } = useTranslation('enrollment')
  const current = status ?? 'Draft'
  const style = STATUS_STYLE[current] ?? STATUS_STYLE.Draft

  return <StatusChip {...style} label={t(`transfers.statuses.${current}`)} />
}
