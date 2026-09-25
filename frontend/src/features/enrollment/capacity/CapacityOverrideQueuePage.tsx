import { useState } from 'react'
import { Alert, Button, Paper, Stack, TextField, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import {
  useDecideOneScopedFullOfferingRequirement,
  useGetApiV1RegistrationCapacityOverrides,
  type GetApiV1RegistrationCapacityOverrides200DataItem,
} from '../../../shared/api/generated/endpoints'

export function CapacityOverrideQueuePage() {
  const { t } = useTranslation('enrollment')
  const query = useGetApiV1RegistrationCapacityOverrides()
  const rows = query.data?.data ?? []

  return (
    <Stack spacing={3}>
      <div>
        <Typography variant="h4" component="h1">
          {t('capacityReview.title')}
        </Typography>
        <Typography color="text.secondary">{t('capacityReview.intro')}</Typography>
      </div>
      {query.isError && <Alert severity="error">{t('capacityReview.loadError')}</Alert>}
      {!query.isPending && !query.isError && rows.length === 0 && (
        <Alert severity="info">{t('capacityReview.empty')}</Alert>
      )}
      {rows.map((row) => (
        <CapacityCard key={row.id} row={row} onChanged={() => query.refetch()} />
      ))}
    </Stack>
  )
}

function CapacityCard({
  row,
  onChanged,
}: {
  row: GetApiV1RegistrationCapacityOverrides200DataItem
  onChanged: () => Promise<unknown>
}) {
  const { t } = useTranslation('enrollment')
  const [reason, setReason] = useState('')
  const decision = useDecideOneScopedFullOfferingRequirement()
  const decide = async (action: 'approve' | 'deny') => {
    if (!row.id || !reason.trim()) return
    await decision.mutateAsync({ requirementId: row.id, data: { action, reason } })
    setReason('')
    await onChanged()
  }

  return (
    <Paper
      variant="outlined"
      sx={(theme) => ({ p: { xs: 2, sm: 3 }, borderRadius: theme.shape.radiusLg })}
    >
      <Stack spacing={2}>
        <Typography variant="h6">{t('capacityReview.course', { id: row.course_id })}</Typography>
        <Typography>
          {t('capacityReview.request', {
            id: row.registration_request_id,
            offering: row.course_offering_id,
          })}
        </Typography>
        <TextField
          label={t('capacityReview.reason')}
          value={reason}
          onChange={(event) => setReason(event.target.value)}
          multiline
          minRows={2}
          required
        />
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
          <Button
            variant="contained"
            disabled={decision.isPending || !reason.trim()}
            onClick={() => decide('approve')}
          >
            {t('capacityReview.approve')}
          </Button>
          <Button
            color="error"
            disabled={decision.isPending || !reason.trim()}
            onClick={() => decide('deny')}
          >
            {t('capacityReview.deny')}
          </Button>
        </Stack>
      </Stack>
    </Paper>
  )
}
