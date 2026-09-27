import { LtrValue } from './RecordValue'
import { useState } from 'react'
import { Alert, Button, Stack, TextField } from '@mui/material'
import { Trans, useTranslation } from 'react-i18next'
import { apiErrorMessage } from '../../shared/api/errors'
import type { OfficialTranscriptRequestDetails200Data } from '../../shared/api/generated/endpoints'

export interface ActionInput {
  reason: string
  reference: string
  amount: string
  receipt?: File
}
export type ServiceAction =
  | 'approve'
  | 'reject'
  | 'payment'
  | 'verify'
  | 'return'
  | 'cancel'
  | 'cancelApprove'
  | 'cancelReject'
  | 'refund'
  | 'collect'
const cancellable = [
  'Submitted',
  'AwaitingPayment',
  'PaymentUnderReview',
  'PaymentCorrectionRequired',
  'ReadyForIssuance',
]

export function RequestActions({
  request,
  staff,
  owner,
  onAction,
}: {
  request: OfficialTranscriptRequestDetails200Data
  staff: boolean
  owner: boolean
  onAction: (action: ServiceAction, input: ActionInput) => Promise<void>
}) {
  const { t } = useTranslation('records')
  const [reason, setReason] = useState('')
  const [reference, setReference] = useState('')
  const [amount, setAmount] = useState('')
  const [receipt, setReceipt] = useState<File>()
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const actions: ServiceAction[] = []
  if (staff && request.status === 'Submitted') actions.push('approve', 'reject')
  if (owner && ['AwaitingPayment', 'PaymentCorrectionRequired'].includes(request.status ?? ''))
    actions.push('payment')
  if (staff && request.status === 'PaymentUnderReview') actions.push('verify', 'return')
  if (owner && cancellable.includes(request.status ?? '')) actions.push('cancel')
  if (staff && request.status === 'CancellationPending')
    actions.push('cancelApprove', 'cancelReject')
  if (staff && request.status === 'RefundPending') actions.push('refund')
  if (staff && request.status === 'ReadyForCollection' && request.delivery_method === 'collection')
    actions.push('collect')
  const requiresReceipt = actions.includes('payment') || actions.includes('refund')
  const requiresAmount = actions.includes('verify') || actions.includes('refund')
  if (!actions.length) return null
  const perform = async (action: ServiceAction) => {
    const reasonRequired = [
      'approve',
      'reject',
      'return',
      'cancelApprove',
      'cancelReject',
    ].includes(action)
    const evidenceRequired = ['payment', 'refund'].includes(action)
    const amountRequired = ['verify', 'refund'].includes(action)
    if (
      (reasonRequired && !reason.trim()) ||
      (evidenceRequired && (!receipt || !reference.trim())) ||
      (amountRequired && (!/^\d{1,7}(\.\d{1,2})?$/.test(amount) || Number(amount) <= 0))
    ) {
      setError(t('service.required'))
      return
    }
    if (
      evidenceRequired &&
      receipt &&
      (receipt.size > 10 * 1024 * 1024 ||
        !['application/pdf', 'image/jpeg', 'image/png'].includes(receipt.type))
    ) {
      setError(t('service.receiptInvalid'))
      return
    }
    setError('')
    setBusy(true)
    try {
      await onAction(action, { reason, reference, amount, receipt })
    } catch (error) {
      setError(apiErrorMessage(error) ?? t('service.actionError'))
    } finally {
      setBusy(false)
    }
  }
  return (
    <Stack spacing={2} sx={{ '@media print': { display: 'none' } }}>
      {actions.some((action) =>
        ['approve', 'reject', 'return', 'cancel', 'cancelApprove', 'cancelReject'].includes(action),
      ) && (
        <TextField
          label={t('service.reason')}
          multiline
          value={reason}
          onChange={(event) => setReason(event.target.value)}
          disabled={busy}
        />
      )}
      {requiresAmount && (
        <TextField
          label={t('service.amount')}
          value={amount}
          onChange={(event) => setAmount(event.target.value)}
          disabled={busy}
          slotProps={{ htmlInput: { inputMode: 'decimal', dir: 'ltr' } }}
          helperText={
            <Trans
              ns="records"
              i18nKey="service.exactAmount"
              values={{ amount: request.refund?.amount ?? request.fee }}
              components={{ amount: <LtrValue /> }}
            />
          }
        />
      )}
      {requiresReceipt && (
        <>
          <TextField
            label={t('service.reference')}
            value={reference}
            onChange={(event) => setReference(event.target.value)}
            disabled={busy}
            slotProps={{ htmlInput: { dir: 'ltr' } }}
          />
          <label>
            {t('service.receipt')}
            <input
              aria-label={t('service.receipt')}
              type="file"
              accept="application/pdf,image/jpeg,image/png"
              disabled={busy}
              onChange={(event) => setReceipt(event.target.files?.[0])}
            />
          </label>
        </>
      )}
      {error && <Alert severity="error">{error}</Alert>}
      <Stack direction="row" useFlexGap sx={{ flexWrap: 'wrap' }} spacing={1}>
        {actions.map((action) => (
          <Button key={action} disabled={busy} onClick={() => void perform(action)}>
            {t(`service.actions.${action}`)}
          </Button>
        ))}
      </Stack>
    </Stack>
  )
}
