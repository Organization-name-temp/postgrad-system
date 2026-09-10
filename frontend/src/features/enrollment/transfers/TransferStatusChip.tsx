import { Chip } from '@mui/material'
import { useTranslation } from 'react-i18next'

const colors = {
  Draft: 'default',
  PendingAdvisor: 'info',
  PendingDepartmentHead: 'warning',
  PendingCouncil: 'warning',
  Returned: 'warning',
  Approved: 'success',
  Rejected: 'error',
  Abandoned: 'default',
} as const

export function TransferStatusChip({ status }: { status?: string }) {
  const { t } = useTranslation('enrollment')
  const known = status && status in colors ? (status as keyof typeof colors) : undefined

  return (
    <Chip
      size="small"
      color={known ? colors[known] : 'default'}
      label={known ? t(`transfers.statuses.${known}`) : status}
    />
  )
}
