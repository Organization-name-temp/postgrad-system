import { useTranslation } from 'react-i18next'
import { StatusChip } from '../../shared/ui/StatusChip'
import { statusToChip, toApplicationStatus } from './applicationStatus'

interface ApplicationStatusChipProps {
  /** The wire status value (snake_case) from the API. */
  status: string | undefined
}

/**
 * The domain-aware status chip for an Application: it maps a status onto the
 * shared `<StatusChip>` presentation via the colocated {@see statusToChip} map and
 * labels it from the `admissions:status` i18n group (bilingual). An unrecognised
 * status falls back to a neutral chip showing the raw value, so a status a later
 * ticket adds still renders rather than crashing.
 */
export function ApplicationStatusChip({ status }: ApplicationStatusChipProps) {
  const { t } = useTranslation('admissions')
  const known = toApplicationStatus(status)

  if (known === null) {
    return <StatusChip tone="neutral" variant="outlined" label={status ?? ''} />
  }

  const { tone, variant } = statusToChip[known]

  return <StatusChip tone={tone} variant={variant} label={t(`status.${known}`)} />
}
