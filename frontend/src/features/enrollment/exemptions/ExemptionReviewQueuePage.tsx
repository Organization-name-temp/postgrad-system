import { useEffect, useState } from 'react'
import {
  Alert,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Paper,
  Pagination,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import DownloadIcon from '@mui/icons-material/Download'
import BlockIcon from '@mui/icons-material/Block'
import WorkspacePremiumOutlinedIcon from '@mui/icons-material/WorkspacePremiumOutlined'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Trans, useTranslation } from 'react-i18next'
import {
  exportAdvisorExemptionQueue,
  exportDepartmentHeadExemptionQueue,
  useAdvisorActionOnACurrentAdviseesRequest,
  useAdvisorManualExemptionQueue,
  useDepartmentHeadManualExemptionQueue,
  useRevokeAnExemptionDecision,
  useScopedDepartmentHeadFinalAction,
} from '../../../shared/api/generated/endpoints'
import { downloadBlob } from '../../../shared/downloadBlob'
import { CreditDecisionEffect } from '../credit-decisions/CreditDecisionEffect'
import { ExemptionEvidenceSummary } from './ExemptionEvidenceSummary'
import { ExemptionStatusChip } from './ExemptionStatusChip'
import { ExemptionTransitionHistory } from './ExemptionTransitionHistory'
import { FormErrorText, reviewReasonSchema, type ReviewReasonValues } from './formSchema'
import type { ReviewerExemptionRequestRow } from './types'
import { useRetryStableActionId } from '../credit-decisions/useRetryStableActionId'

export function ExemptionReviewQueuePage({ stage }: { stage: 'advisor' | 'departmentHead' }) {
  const { t } = useTranslation('enrollment')
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const queueParams = {
    q: search || undefined,
    page,
    include_decided: stage === 'departmentHead' ? true : undefined,
  }
  const advisor = useAdvisorManualExemptionQueue(queueParams, {
    query: { enabled: stage === 'advisor' },
  })
  const head = useDepartmentHeadManualExemptionQueue(queueParams, {
    query: { enabled: stage === 'departmentHead' },
  })
  const query = stage === 'advisor' ? advisor : head
  const rows = (query.data?.data ?? []) as ReviewerExemptionRequestRow[]
  const lastPage = Math.max(1, query.data?.meta?.last_page ?? 1)
  useEffect(() => {
    if (!query.isPending && page > lastPage) setPage(lastPage)
  }, [lastPage, page, query.isPending])
  const queueViewState = query.isError
    ? 'error'
    : query.isPending
      ? 'loading'
      : rows.length === 0
        ? 'empty'
        : 'content'
  const downloadReview = async () => {
    const blob =
      stage === 'advisor'
        ? await exportAdvisorExemptionQueue(queueParams)
        : await exportDepartmentHeadExemptionQueue(queueParams)
    downloadBlob(blob, 'exemption-review.xlsx')
  }

  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" component="h1">
          {t(`manualExemptions.queue.${stage}.title`)}
        </Typography>
        <Typography color="text.secondary">{t(`manualExemptions.queue.${stage}.intro`)}</Typography>
      </Box>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
        <TextField
          label={t('manualExemptions.search')}
          value={search}
          onChange={(event) => {
            setSearch(event.target.value)
            setPage(1)
          }}
        />
        <Button startIcon={<DownloadIcon />} onClick={() => void downloadReview()}>
          {t('manualExemptions.export')}
        </Button>
      </Stack>
      {queueViewState === 'loading' && (
        <Stack spacing={2} aria-label={t('manualExemptions.loading')}>
          <Skeleton variant="rounded" height={220} />
          <Skeleton variant="rounded" height={220} />
        </Stack>
      )}
      {queueViewState === 'error' && (
        <Alert
          severity="error"
          action={
            <Button color="inherit" onClick={() => void query.refetch()}>
              {t('manualExemptions.retry')}
            </Button>
          }
        >
          {t('manualExemptions.loadError')}
        </Alert>
      )}
      {queueViewState === 'empty' && (
        <Stack sx={{ alignItems: 'center', py: 4 }} spacing={1}>
          <WorkspacePremiumOutlinedIcon color="disabled" fontSize="large" />
          <Typography color="text.secondary" sx={{ textAlign: 'center' }}>
            {t('manualExemptions.queue.empty')}
          </Typography>
        </Stack>
      )}
      {queueViewState === 'content' &&
        rows.map((row) => (
          <ReviewCard
            key={row.id}
            row={row}
            stage={stage}
            onChanged={() => queryClient.invalidateQueries()}
          />
        ))}
      {!query.isPending && !query.isError && lastPage > 1 && (
        <Pagination
          page={page}
          count={lastPage}
          onChange={(_, nextPage) => setPage(nextPage)}
          sx={{ alignSelf: 'center' }}
        />
      )}
    </Stack>
  )
}

function ReviewCard({
  row,
  stage,
  onChanged,
}: {
  row: ReviewerExemptionRequestRow
  stage: 'advisor' | 'departmentHead'
  onChanged: () => Promise<unknown>
}) {
  const { t } = useTranslation('enrollment')
  const [confirmReject, setConfirmReject] = useState(false)
  const [confirmRevoke, setConfirmRevoke] = useState(false)
  const reviewForm = useForm<ReviewReasonValues>({
    resolver: zodResolver(reviewReasonSchema),
    mode: 'onBlur',
    defaultValues: { reason: '' },
  })
  const advisorAction = useAdvisorActionOnACurrentAdviseesRequest()
  const headAction = useScopedDepartmentHeadFinalAction()
  const revoke = useRevokeAnExemptionDecision()
  const actionId = useRetryStableActionId()
  const actionError = advisorAction.isError || headAction.isError || revoke.isError
  const actionPending = advisorAction.isPending || headAction.isPending || revoke.isPending

  const act = async (action: string) => {
    if (!(await reviewForm.trigger('reason'))) return
    const reason = reviewForm.getValues('reason').trim()
    if (['return', 'reject'].includes(action) && !reason) {
      reviewForm.setError('reason', { message: t('manualExemptions.required') })
      return
    }
    reviewForm.clearErrors('reason')
    const key = JSON.stringify([stage, row.id, row.revision, action, reason])
    const input = {
      exemptionRequestId: row.id ?? 0,
      data: {
        action,
        reason: reason || undefined,
        expected_revision: row.revision ?? 1,
        action_id: actionId.forAction(key),
      },
    }
    try {
      if (stage === 'advisor') await advisorAction.mutateAsync(input)
      else await headAction.mutateAsync(input)
      actionId.complete(key)
      reviewForm.reset()
      await onChanged()
    } catch {
      // Mutation state renders the retryable error; keep the same action id for retry.
    }
  }
  const confirmRejection = async () => {
    if (!(await reviewForm.trigger('reason'))) return
    if (!reviewForm.getValues('reason').trim()) {
      reviewForm.setError('reason', { message: t('manualExemptions.required') })
      return
    }
    setConfirmReject(true)
  }
  const revokeDecision = async () => {
    const reason = reviewForm.getValues('reason').trim()
    if (!reason) {
      reviewForm.setError('reason', { message: t('manualExemptions.required') })
      return
    }
    await revoke.mutateAsync({ decisionId: row.decision?.id ?? 0, data: { reason } })
    setConfirmRevoke(false)
    reviewForm.reset()
    await onChanged()
  }

  return (
    <Paper
      variant="outlined"
      sx={(theme) => ({ p: { xs: 2, sm: 3 }, borderRadius: theme.shape.radiusLg })}
    >
      <Stack spacing={2}>
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          sx={{ justifyContent: 'space-between', gap: 1 }}
        >
          <Box>
            <Typography variant="h6">{row.student?.name}</Typography>
            <Typography color="text.secondary" dir="ltr">
              {row.student?.university_id} · {row.target_course_code}
            </Typography>
          </Box>
          <ExemptionStatusChip status={row.status} />
        </Stack>
        <Typography>{t(`manualExemptions.roles.${row.preparatory_requirement_role}`)}</Typography>
        <ExemptionEvidenceSummary evidence={row.evidence} />
        {row.student_note && <Typography>{row.student_note}</Typography>}
        <ExemptionTransitionHistory transitions={row.transitions} />
        {row.decision && (
          <CreditDecisionEffect
            effective={row.decision.effective}
            revocation={row.decision.revocation}
          >
            {t('manualExemptions.decision', { count: row.decision.credit_hours })}
          </CreditDecisionEffect>
        )}
        <TextField
          {...reviewForm.register('reason')}
          label={t('manualExemptions.reviewerReason')}
          multiline
          minRows={2}
          error={Boolean(reviewForm.formState.errors.reason)}
          helperText={
            reviewForm.formState.errors.reason ? (
              <FormErrorText
                message={
                  reviewForm.formState.errors.reason.message === 'too_long'
                    ? t('manualExemptions.tooLong')
                    : reviewForm.formState.errors.reason.message
                }
              />
            ) : (
              t('manualExemptions.reviewerReasonHelp')
            )
          }
        />
        {actionError && <Alert severity="error">{t('manualExemptions.actionError')}</Alert>}
        {row.status === 'PendingDepartmentHead' || stage !== 'departmentHead' ? (
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
            {stage === 'advisor' ? (
              <Button
                variant="contained"
                disabled={actionPending}
                onClick={() => void act('recommend')}
              >
                {t('manualExemptions.review.recommend')}
              </Button>
            ) : (
              <Button
                variant="contained"
                disabled={actionPending}
                onClick={() => void act('approve')}
              >
                {t('manualExemptions.review.approve')}
              </Button>
            )}
            <Button disabled={actionPending} onClick={() => void act('return')}>
              {t('manualExemptions.review.return')}
            </Button>
            <Button
              color="error"
              variant="contained"
              startIcon={<BlockIcon />}
              disabled={actionPending}
              onClick={() => void confirmRejection()}
            >
              {t('manualExemptions.review.reject')}
            </Button>
          </Stack>
        ) : (
          row.decision &&
          row.decision.effective !== false && (
            <Button
              color="error"
              variant="outlined"
              startIcon={<BlockIcon />}
              disabled={actionPending}
              onClick={() => setConfirmRevoke(true)}
            >
              {t('creditDecisions.revoke')}
            </Button>
          )
        )}
        <Dialog
          open={confirmReject}
          onClose={() => setConfirmReject(false)}
          aria-labelledby={`reject-title-${row.id}`}
        >
          <DialogTitle id={`reject-title-${row.id}`}>
            {t('manualExemptions.review.reject')}
          </DialogTitle>
          <DialogContent>
            <DialogContentText>
              <Trans
                t={t}
                i18nKey="manualExemptions.rejectConfirm"
                values={{ course: row.target_course_code }}
                components={{ code: <bdi dir="ltr" /> }}
              />
            </DialogContentText>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setConfirmReject(false)}>{t('manualExemptions.cancel')}</Button>
            <Button
              color="error"
              variant="contained"
              startIcon={<BlockIcon />}
              onClick={() => {
                setConfirmReject(false)
                void act('reject')
              }}
            >
              {t('manualExemptions.confirmReject')}
            </Button>
          </DialogActions>
        </Dialog>
        <Dialog open={confirmRevoke} onClose={() => setConfirmRevoke(false)}>
          <DialogTitle>{t('creditDecisions.revoke')}</DialogTitle>
          <DialogContent>
            <DialogContentText>{t('creditDecisions.revokeConfirm')}</DialogContentText>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setConfirmRevoke(false)}>{t('manualExemptions.cancel')}</Button>
            <Button color="error" variant="contained" onClick={() => void revokeDecision()}>
              {t('creditDecisions.confirmRevoke')}
            </Button>
          </DialogActions>
        </Dialog>
      </Stack>
    </Paper>
  )
}
