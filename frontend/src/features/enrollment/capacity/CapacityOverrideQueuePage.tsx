import { useState } from 'react'
import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Paper,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import BlockIcon from '@mui/icons-material/Block'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import {
  useDecideOneScopedFullOfferingRequirement,
  useGetApiV1RegistrationCapacityOverrides,
  type GetApiV1RegistrationCapacityOverrides200DataItem,
} from '../../../shared/api/generated/endpoints'

const decisionSchema = z.object({ reason: z.string().trim().min(1).max(4000) })
type DecisionValues = z.infer<typeof decisionSchema>

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
      {query.isError && (
        <Alert
          severity="error"
          action={<Button onClick={() => void query.refetch()}>{t('capacityReview.retry')}</Button>}
        >
          {t('capacityReview.loadError')}
        </Alert>
      )}
      {query.isPending && (
        <Stack role="status" spacing={2} aria-label={t('capacityReview.loading')}>
          <Skeleton variant="rounded" height={220} />
          <Skeleton variant="rounded" height={220} />
        </Stack>
      )}
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
  const [confirmDeny, setConfirmDeny] = useState(false)
  const form = useForm<DecisionValues>({
    resolver: zodResolver(decisionSchema),
    defaultValues: { reason: '' },
  })
  const decision = useDecideOneScopedFullOfferingRequirement()
  const decide = async (action: 'approve' | 'deny') => {
    if (!row.id || !(await form.trigger())) return
    const reason = form.getValues('reason').trim()
    await decision.mutateAsync({ requirementId: row.id, data: { action, reason } })
    form.reset()
    await onChanged()
  }

  return (
    <Paper
      variant="outlined"
      sx={(theme) => ({ p: { xs: 2, sm: 3 }, borderRadius: theme.shape.radiusLg })}
    >
      <Stack spacing={2}>
        <Typography variant="h6" component="h2">
          {t('capacityReview.courseLabel')} <bdi dir="ltr">#{row.course_id}</bdi>
        </Typography>
        {row.obligation_type && (
          <Typography>{t(`capacityReview.obligationTypes.${row.obligation_type}`)}</Typography>
        )}
        <Typography>
          {t('capacityReview.requestLabel')} <bdi dir="ltr">#{row.registration_request_id}</bdi> ·{' '}
          {t('capacityReview.offeringLabel')} <bdi dir="ltr">#{row.course_offering_id}</bdi>
        </Typography>
        <TextField
          label={t('capacityReview.reason')}
          {...form.register('reason')}
          error={Boolean(form.formState.errors.reason)}
          helperText={form.formState.errors.reason ? t('capacityReview.required') : undefined}
          multiline
          minRows={2}
          required
        />
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
          <Button
            variant="contained"
            disabled={decision.isPending}
            onClick={() => decide('approve')}
          >
            {t('capacityReview.approve')}
          </Button>
          <Button color="error" disabled={decision.isPending} onClick={() => setConfirmDeny(true)}>
            {t('capacityReview.deny')}
          </Button>
        </Stack>
      </Stack>
      <Dialog open={confirmDeny} onClose={() => setConfirmDeny(false)}>
        <DialogTitle>{t('capacityReview.deny')}</DialogTitle>
        <DialogContent>
          <DialogContentText>
            {t('capacityReview.denyConfirm', {
              request: row.registration_request_id,
              course: row.course_id,
            })}
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirmDeny(false)}>{t('capacityReview.cancel')}</Button>
          <Button
            color="error"
            variant="contained"
            startIcon={<BlockIcon />}
            onClick={() => {
              setConfirmDeny(false)
              void decide('deny')
            }}
          >
            {t('capacityReview.deny')}
          </Button>
        </DialogActions>
      </Dialog>
    </Paper>
  )
}
