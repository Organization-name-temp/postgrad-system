import { LtrValue, RecordActor, RecordTime } from './RecordValue'
import { RecordStatus } from './RecordStatus'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Alert, Button, Checkbox, FormControlLabel, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { usePermissions } from '../../shared/access/usePermissions'
import { apiErrorMessage } from '../../shared/api/errors'
import { downloadBlob } from '../../shared/downloadBlob'
import * as api from '../../shared/api/generated/endpoints'
import { RequestActions, type ActionInput, type ServiceAction } from './RequestActions'
import { TranscriptDocument } from './TranscriptDocument'

export function RequestPage() {
  const { requestId } = useParams()
  const id = Number(requestId)
  const { t, i18n } = useTranslation('records')
  const permissions = usePermissions()
  const staff = permissions.isAdmin || permissions.hasRole('R05')
  const query = api.useOfficialTranscriptRequestDetails(id)
  const request = query.data?.data
  const owner = permissions.hasRole('R02') && request?.is_owner === true
  const [preview, setPreview] = useState<api.ReviewCoherentOfficialTranscriptPreview200Data>()
  const [reviewed, setReviewed] = useState(false)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const action = async (action: ServiceAction, input: ActionInput) => {
    switch (action) {
      case 'approve':
      case 'reject':
        await api.reviewOfficialTranscriptRequest(id, { decision: action, reason: input.reason })
        break
      case 'cancel':
        await api.requestTranscriptCancellation(id, { reason: input.reason })
        break
      case 'cancelApprove':
      case 'cancelReject':
        await api.decideTranscriptCancellation(id, {
          decision: action === 'cancelApprove' ? 'approve' : 'reject',
          reason: input.reason,
        })
        break
      case 'payment':
        if (!input.receipt) throw new Error('Receipt required')
        await api.submitTranscriptPaymentEvidence(id, {
          reference: input.reference,
          receipt: input.receipt,
        })
        break
      case 'verify':
      case 'return':
        await api.verifyOrReturnTranscriptPayment(id, {
          decision: action,
          reason: input.reason,
          amount: input.amount,
        })
        break
      case 'refund':
        if (!input.receipt) throw new Error('Receipt required')
        await api.completeFullManualTranscriptRefund(id, {
          reference: input.reference,
          receipt: input.receipt,
          amount: input.amount,
        })
        break
      case 'collect':
        await api.recordCollectionHandover(id)
        break
    }
    setPreview(undefined)
    setReviewed(false)
    await query.refetch()
  }
  const refreshPreview = async () => {
    setPreview(undefined)
    setReviewed(false)
    setError('')
    setBusy(true)
    try {
      setPreview((await api.reviewCoherentOfficialTranscriptPreview(id)).data)
    } catch (error) {
      setError(apiErrorMessage(error) ?? t('service.blocked'))
    } finally {
      setBusy(false)
    }
  }
  if (query.isPending) return <Typography>{t('loading')}</Typography>
  if (query.isError || !request) return <Alert severity="error">{t('loadError')}</Alert>
  return (
    <Stack spacing={2}>
      <Typography variant="h4" component="h1">
        {t('service.title')} <LtrValue>#{request.id}</LtrValue>
      </Typography>
      <RecordStatus status={request.status} />
      <Typography>
        <LtrValue>{request.fee} EGP</LtrValue> · {t(`service.${request.delivery_method}`)}
      </Typography>
      {request.cancellation_prior_status && (
        <Alert severity="info">
          {t('service.cancelledStage', {
            status: t(`service.statuses.${request.cancellation_prior_status}`),
          })}
        </Alert>
      )}
      {(request.instructions_en || request.instructions_ar) && (
        <>
          <Typography variant="h6">{t('service.instructions')}</Typography>
          <Typography sx={{ whiteSpace: 'pre-wrap' }}>
            {i18n.language === 'ar' ? request.instructions_ar : request.instructions_en}
          </Typography>
        </>
      )}
      <RequestActions
        key={request.status}
        request={request}
        staff={staff}
        owner={owner}
        onAction={action}
      />
      <Typography variant="h6">{t('service.payments')}</Typography>
      {request.payments?.map((payment) => (
        <Stack key={payment.id}>
          <Typography dir="ltr">
            {payment.reference} · <RecordTime value={payment.submitted_at} />
          </Typography>
          {payment.decision && (
            <Typography>
              {t(`service.actions.${payment.decision.decision}`)} · {payment.decision.reason} ·{' '}
              <RecordActor id={payment.decision.actor_user_id} /> ·{' '}
              <RecordTime value={payment.decision.at} />
            </Typography>
          )}
          <Button
            onClick={() => {
              void api
                .privateTranscriptPaymentReceipt(id, payment.id ?? 0)
                .then((blob) => downloadBlob(blob, 'payment-receipt'))
                .catch(() => setError(t('loadError')))
            }}
          >
            {t('service.receiptDownload')}
          </Button>
        </Stack>
      ))}
      {request.verified_payment && (
        <Typography>
          {t('service.verified')}: <LtrValue>{request.verified_payment.amount} EGP</LtrValue> ·{' '}
          <RecordActor id={request.verified_payment.verified_by} /> ·{' '}
          <RecordTime value={request.verified_payment.verified_at} />
        </Typography>
      )}
      {request.refund && (
        <Stack>
          <Typography>
            {t('service.refund')}: <LtrValue>{request.refund.amount} EGP</LtrValue> ·{' '}
            <LtrValue>{request.refund.reference}</LtrValue> ·{' '}
            <RecordTime value={request.refund.completed_at} />
          </Typography>
          {request.refund.completed_at && (
            <Button
              onClick={() => {
                void api
                  .privateTranscriptRefundReceipt(id)
                  .then((blob) => downloadBlob(blob, 'refund-receipt'))
                  .catch(() => setError(t('loadError')))
              }}
            >
              {t('service.receiptDownload')}
            </Button>
          )}
        </Stack>
      )}
      {request.issuance_id && (
        <Button component={Link} to={`/records/issuances/${request.issuance_id}`}>
          {t('service.official')}
        </Button>
      )}
      <Typography variant="h6">{t('service.transitions')}</Typography>
      {request.transitions?.map((transition) => (
        <Stack
          key={transition.id}
          direction="row"
          spacing={1}
          sx={{ flexWrap: 'wrap', alignItems: 'center' }}
        >
          <RecordStatus status={transition.to_status} />
          <Typography>
            {transition.reason} · <RecordActor id={transition.actor_user_id} /> ·{' '}
            <RecordTime value={transition.at} />
          </Typography>
        </Stack>
      ))}
      {staff && request.status === 'ReadyForIssuance' && (
        <Stack spacing={2}>
          <Button disabled={busy} onClick={() => void refreshPreview()}>
            {t('service.preview')}
          </Button>
          {preview?.snapshot && (
            <>
              <Alert severity="info">{t('service.reviewRequired')}</Alert>
              <div lang="en" dir="ltr">
                <TranscriptDocument record={preview.snapshot} language="en" official />
              </div>
              <div lang="ar" dir="rtl">
                <TranscriptDocument record={preview.snapshot} language="ar" official />
              </div>
              <FormControlLabel
                control={
                  <Checkbox
                    checked={reviewed}
                    onChange={(event) => setReviewed(event.target.checked)}
                  />
                }
                label={t('service.confirmReview')}
              />
              <Button
                disabled={!reviewed || busy}
                onClick={() => {
                  if (!preview.review_token) return
                  setBusy(true)
                  setError('')
                  void api
                    .certifyReviewedOfficialTranscript(id, { review_token: preview.review_token })
                    .then(async () => {
                      setPreview(undefined)
                      setReviewed(false)
                      await query.refetch()
                    })
                    .catch((error) => {
                      setPreview(undefined)
                      setReviewed(false)
                      setError(apiErrorMessage(error) ?? t('service.stale'))
                    })
                    .finally(() => setBusy(false))
                }}
              >
                {t('service.issue')}
              </Button>
            </>
          )}
        </Stack>
      )}
      <Button
        onClick={() => {
          setPreview(undefined)
          setReviewed(false)
          void query.refetch()
        }}
      >
        {t('service.refresh')}
      </Button>
      {error && <Alert severity="error">{error}</Alert>}
    </Stack>
  )
}
