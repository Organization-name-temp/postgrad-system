import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Alert, Button, MenuItem, Pagination, Stack, TextField, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { usePermissions } from '../../shared/access/usePermissions'
import {
  useOfficialTranscriptRequestQueueAndHistory,
  useSubmitOfficialTranscriptRequest,
  useTranscriptServiceAvailability,
  exportOfficialTranscriptRequestQueueAndHistory,
} from '../../shared/api/generated/endpoints'
import { downloadBlob } from '../../shared/downloadBlob'
import { apiErrorMessage } from '../../shared/api/errors'

export const requestStatuses = [
  'Submitted',
  'AwaitingPayment',
  'PaymentUnderReview',
  'PaymentCorrectionRequired',
  'ReadyForIssuance',
  'CancellationPending',
  'RefundPending',
  'ReadyForCollection',
  'Completed',
  'Rejected',
  'Cancelled',
]
export function RequestsPage() {
  const { t } = useTranslation('records')
  const { hasRole, isAdmin } = usePermissions()
  const permitted = hasRole('R02') || hasRole('R05') || isAdmin
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [delivery, setDelivery] = useState('digital')
  const [page, setPage] = useState(1)
  const [error, setError] = useState('')
  const params = { q: search, status, page, per_page: 25 }
  const queue = useOfficialTranscriptRequestQueueAndHistory(params, {
    query: { enabled: permitted },
  })
  const service = useTranscriptServiceAvailability()
  const submit = useSubmitOfficialTranscriptRequest()
  const open = Boolean(service.data?.data?.open_request_id)
  return (
    <Stack spacing={2}>
      <Typography variant="h4" component="h1">
        {t('service.title')}
      </Typography>
      <Button component={Link} to="/records/history">
        {t('service.history')}
      </Button>
      {!permitted && <Alert severity="info">{t('service.history')}</Alert>}
      {hasRole('R02') && (
        <Stack
          component="form"
          spacing={2}
          onSubmit={(event) => {
            event.preventDefault()
            setError('')
            void submit
              .mutateAsync({ data: { delivery_method: delivery } })
              .then(async () => {
                await queue.refetch()
                await service.refetch()
              })
              .catch((error) => setError(apiErrorMessage(error) ?? t('service.actionError')))
          }}
        >
          <Typography variant="h6">{t('service.newRequest')}</Typography>
          {service.data?.data?.available ? (
            <Typography>{t('feeAvailable', { fee: service.data.data.fee })}</Typography>
          ) : (
            <Alert severity="warning">{t('serviceUnavailable')}</Alert>
          )}
          <TextField
            select
            label={t('service.delivery')}
            value={delivery}
            onChange={(event) => setDelivery(event.target.value)}
          >
            <MenuItem value="digital">{t('service.digital')}</MenuItem>
            <MenuItem value="collection">{t('service.collection')}</MenuItem>
          </TextField>
          <Button
            type="submit"
            disabled={!service.data?.data?.available || open || submit.isPending}
          >
            {t('service.submit')}
          </Button>
        </Stack>
      )}
      {permitted && (
        <>
          <TextField
            label={t('search')}
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setPage(1)
            }}
          />
          <TextField
            select
            label={t('service.status')}
            value={status}
            onChange={(event) => {
              setStatus(event.target.value)
              setPage(1)
            }}
          >
            <MenuItem value="">{t('service.allStatuses')}</MenuItem>
            {requestStatuses.map((value) => (
              <MenuItem key={value} value={value}>
                {t(`service.statuses.${value}`)}
              </MenuItem>
            ))}
          </TextField>
          <Button
            onClick={() => {
              void exportOfficialTranscriptRequestQueueAndHistory(params)
                .then((blob) => downloadBlob(blob, 'transcript-requests.xlsx'))
                .catch(() => setError(t('loadError')))
            }}
          >
            {t('service.exportRequests')}
          </Button>
          {queue.data?.data?.map((request) => (
            <Stack key={request.id} direction="row" spacing={2} sx={{ alignItems: 'center' }}>
              <Typography>
                {request.id} · {t(`service.statuses.${request.status}`)} · {request.fee} EGP ·{' '}
                {t(`service.${request.delivery_method}`)}
              </Typography>
              <Button component={Link} to={`/records/requests/${request.id}`}>
                {t('service.open')}
              </Button>
            </Stack>
          ))}
          <Pagination
            count={queue.data?.meta?.last_page ?? 1}
            page={page}
            onChange={(_, value) => setPage(value)}
          />
          {queue.isPending && <Typography>{t('loading')}</Typography>}
          {queue.isError && <Alert severity="error">{t('loadError')}</Alert>}
        </>
      )}
      {error && <Alert severity="error">{error}</Alert>}
    </Stack>
  )
}
