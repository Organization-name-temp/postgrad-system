import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { Alert, Button, Stack, TextField, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import {
  useImmutableOfficialTranscriptDetail,
  revokeOfficialCopyValidity,
} from '../../shared/api/generated/endpoints'
import { usePermissions } from '../../shared/access/usePermissions'
import { apiErrorMessage } from '../../shared/api/errors'
import { IssuedDocument } from './IssuedDocument'

export function IssuancePage() {
  const { issuanceId } = useParams()
  const { t } = useTranslation('records')
  const query = useImmutableOfficialTranscriptDetail(Number(issuanceId))
  const { isAdmin, hasRole } = usePermissions()
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  if (query.isPending) return <Typography>{t('loading')}</Typography>
  if (query.isError || !query.data?.data) return <Alert severity="error">{t('loadError')}</Alert>
  const issuance = query.data.data
  return (
    <Stack spacing={2}>
      <Typography variant="h4" component="h1">
        {t('service.official')}
      </Typography>
      <Stack spacing={2} sx={{ '@media print': { display: 'none' } }}>
        <Button onClick={() => window.print()}>{t('print')}</Button>
        <Button onClick={() => void query.refetch()}>{t('service.refresh')}</Button>
        {(isAdmin || hasRole('R05')) && issuance.status !== 'Revoked' && (
          <>
            <TextField
              label={t('service.revokeReason')}
              multiline
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              disabled={busy}
            />
            <Button
              disabled={!reason.trim() || busy}
              onClick={() => {
                setBusy(true)
                setError('')
                void revokeOfficialCopyValidity(Number(issuanceId), { reason })
                  .then(() => query.refetch())
                  .catch((error) => setError(apiErrorMessage(error) ?? t('service.actionError')))
                  .finally(() => setBusy(false))
              }}
            >
              {t('service.revoke')}
            </Button>
          </>
        )}
        {issuance.revocation && (
          <Typography>
            {issuance.revocation.reason} ·{' '}
            {t('service.actor', { id: issuance.revocation.actor_user_id })} ·{' '}
            {issuance.revocation.at}
          </Typography>
        )}
        {error && <Alert severity="error">{error}</Alert>}
      </Stack>
      <IssuedDocument
        issuance={issuance}
        verificationPath={(issuance.verification_url ?? '').replace(
          '/api/v1/records/verify/',
          '/records/verify/',
        )}
      />
    </Stack>
  )
}
