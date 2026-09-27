import { useTranslation } from 'react-i18next'
import { StatusChip, type StatusTone, type StatusChipVariant } from '../../shared/ui/StatusChip'

const statuses: Record<string, { tone: StatusTone; variant: StatusChipVariant }> = {
  Submitted: { tone: 'info', variant: 'filled-soft' },
  AwaitingPayment: { tone: 'warning', variant: 'filled-soft' },
  PaymentUnderReview: { tone: 'secondary', variant: 'filled-soft' },
  PaymentCorrectionRequired: { tone: 'warning', variant: 'filled-soft' },
  ReadyForIssuance: { tone: 'success', variant: 'filled-soft' },
  CancellationPending: { tone: 'warning', variant: 'filled-soft' },
  RefundPending: { tone: 'warning', variant: 'filled-soft' },
  ReadyForCollection: { tone: 'info', variant: 'filled-soft' },
  Completed: { tone: 'success', variant: 'filled' },
  Rejected: { tone: 'error', variant: 'filled' },
  Cancelled: { tone: 'neutral', variant: 'outlined' },
  Valid: { tone: 'success', variant: 'filled-soft' },
  Revoked: { tone: 'error', variant: 'filled' },
}

export function RecordStatus({ status }: { status?: string }) {
  const { t } = useTranslation('records')
  return (
    <StatusChip
      {...(statuses[status ?? ''] ?? { tone: 'neutral', variant: 'outlined' })}
      label={t(`service.statuses.${status}`)}
    />
  )
}
