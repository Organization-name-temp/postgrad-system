import { useEffect, useState } from 'react'
import BlockIcon from '@mui/icons-material/Block'
import DownloadIcon from '@mui/icons-material/Download'
import SwapHorizIcon from '@mui/icons-material/SwapHoriz'
import {
  Alert,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  FormControl,
  FormHelperText,
  InputLabel,
  MenuItem,
  Pagination,
  Paper,
  Select,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { Controller, useForm } from 'react-hook-form'
import { Trans, useTranslation } from 'react-i18next'
import {
  exportAdvisorTransferQueue,
  exportCouncilTransferQueue,
  exportDepartmentHeadTransferQueue,
  type AdvisorTransferQueue200DataItem,
  useAdvisorActionOnTransferRequest,
  useAdvisorTransferQueue,
  useCouncilFinalTransferAction,
  useCouncilTransferQueue,
  useDepartmentHeadTransferQueue,
  useScopedHeadActionOnTransferRequest,
} from '../../../shared/api/generated/endpoints'
import { downloadBlob } from '../credit-decisions/downloadBlob'
import { useRetryStableActionId } from '../credit-decisions/useRetryStableActionId'
import { FormErrorText, transferReviewSchema, type TransferReviewValues } from './formSchema'
import { TransferEvidenceSummary } from './TransferEvidenceSummary'
import { TransferHistory } from './TransferHistory'
import { TransferStatusChip } from './TransferStatusChip'

export type TransferReviewStage = 'advisor' | 'departmentHead' | 'council'

export function TransferReviewQueuePage({ stage }: { stage: TransferReviewStage }) {
  const { t } = useTranslation('enrollment')
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const params = { q: search || undefined, sort: 'submitted_at', page }
  const advisor = useAdvisorTransferQueue(params, { query: { enabled: stage === 'advisor' } })
  const head = useDepartmentHeadTransferQueue(params, {
    query: { enabled: stage === 'departmentHead' },
  })
  const council = useCouncilTransferQueue(params, { query: { enabled: stage === 'council' } })
  const query = stage === 'advisor' ? advisor : stage === 'departmentHead' ? head : council
  const rows: AdvisorTransferQueue200DataItem[] = query.data?.data ?? []
  const lastPage = Math.max(1, query.data?.meta?.last_page ?? 1)

  useEffect(() => {
    if (!query.isPending && page > lastPage) setPage(lastPage)
  }, [lastPage, page, query.isPending])

  const download = async () => {
    const blob =
      stage === 'advisor'
        ? await exportAdvisorTransferQueue(params)
        : stage === 'departmentHead'
          ? await exportDepartmentHeadTransferQueue(params)
          : await exportCouncilTransferQueue(params)
    downloadBlob(blob, `transfer-${stage}-queue.xlsx`)
  }
  const state = query.isError
    ? 'error'
    : query.isPending
      ? 'loading'
      : rows.length === 0
        ? 'empty'
        : 'content'

  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" component="h1">
          {t(`transfers.queue.${stage}.title`)}
        </Typography>
        <Typography color="text.secondary">{t(`transfers.queue.${stage}.intro`)}</Typography>
      </Box>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
        <TextField
          label={t('transfers.search')}
          value={search}
          onChange={(event) => {
            setSearch(event.target.value)
            setPage(1)
          }}
        />
        <Button startIcon={<DownloadIcon />} onClick={() => void download()}>
          {t('transfers.export')}
        </Button>
      </Stack>
      {state === 'loading' && (
        <Stack spacing={2} aria-label={t('transfers.loading')}>
          <Skeleton variant="rounded" height={260} />
          <Skeleton variant="rounded" height={260} />
        </Stack>
      )}
      {state === 'error' && (
        <Alert
          severity="error"
          action={
            <Button color="inherit" onClick={() => void query.refetch()}>
              {t('transfers.retry')}
            </Button>
          }
        >
          {t('transfers.loadError')}
        </Alert>
      )}
      {state === 'empty' && (
        <Stack sx={{ alignItems: 'center', py: 4 }} spacing={1}>
          <SwapHorizIcon color="disabled" fontSize="large" />
          <Typography color="text.secondary">{t('transfers.queue.empty')}</Typography>
        </Stack>
      )}
      {state === 'content' &&
        rows.map((row) => (
          <TransferReviewCard
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
          onChange={(_, next) => setPage(next)}
          sx={{ alignSelf: 'center' }}
        />
      )}
    </Stack>
  )
}

function TransferReviewCard({
  row,
  stage,
  onChanged,
}: {
  row: AdvisorTransferQueue200DataItem
  stage: TransferReviewStage
  onChanged: () => Promise<unknown>
}) {
  const { t } = useTranslation('enrollment')
  const [confirmReject, setConfirmReject] = useState(false)
  const form = useForm<TransferReviewValues>({
    resolver: zodResolver(transferReviewSchema),
    mode: 'onBlur',
    defaultValues: { reason: '', normalizedGrade: '' },
  })
  const advisorAction = useAdvisorActionOnTransferRequest()
  const headAction = useScopedHeadActionOnTransferRequest()
  const councilAction = useCouncilFinalTransferAction()
  const actionId = useRetryStableActionId()
  const pending = advisorAction.isPending || headAction.isPending || councilAction.isPending
  const failed = advisorAction.isError || headAction.isError || councilAction.isError
  const id = row.id ?? 0
  const revision = row.revision ?? 1

  const act = async (action: string) => {
    if (!(await form.trigger())) return
    const { reason, normalizedGrade } = form.getValues()
    if (['return', 'reject'].includes(action) && !reason.trim()) {
      form.setError('reason', { message: 'required' })
      return
    }
    if (stage === 'departmentHead' && action === 'verify' && !normalizedGrade) {
      form.setError('normalizedGrade', { message: 'required' })
      return
    }
    const key = JSON.stringify([stage, id, revision, action, reason, normalizedGrade])
    const common = {
      action,
      reason: reason.trim() || undefined,
      expected_revision: revision,
      action_id: actionId.forAction(key),
    }
    try {
      if (stage === 'advisor')
        await advisorAction.mutateAsync({ transferRequestId: id, data: common })
      else if (stage === 'departmentHead')
        await headAction.mutateAsync({
          transferRequestId: id,
          data: { ...common, normalized_grade: normalizedGrade || undefined },
        })
      else await councilAction.mutateAsync({ transferRequestId: id, data: common })
      actionId.complete(key)
      form.reset()
      await onChanged()
    } catch {
      // The mutation state renders the retryable error and retains this action id.
    }
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
          <TransferStatusChip status={row.status} />
        </Stack>
        <TransferEvidenceSummary evidence={row} />
        {row.student_note && <Typography>{row.student_note}</Typography>}
        <TransferHistory transitions={row.transitions} />
        {stage === 'departmentHead' && (
          <FormControl fullWidth error={Boolean(form.formState.errors.normalizedGrade)}>
            <InputLabel id={`transfer-grade-${id}`}>{t('transfers.normalizedGrade')}</InputLabel>
            <Controller
              control={form.control}
              name="normalizedGrade"
              render={({ field }) => (
                <Select
                  {...field}
                  labelId={`transfer-grade-${id}`}
                  label={t('transfers.normalizedGrade')}
                >
                  {['A+', 'A', 'B+', 'B'].map((grade) => (
                    <MenuItem key={grade} value={grade}>
                      {grade}
                    </MenuItem>
                  ))}
                </Select>
              )}
            />
            <FormHelperText>
              <FormErrorText
                message={
                  form.formState.errors.normalizedGrade ? t('transfers.required') : undefined
                }
              />
            </FormHelperText>
          </FormControl>
        )}
        <TextField
          {...form.register('reason')}
          label={t('transfers.reviewerReason')}
          multiline
          minRows={2}
          error={Boolean(form.formState.errors.reason)}
          helperText={
            form.formState.errors.reason ? (
              <FormErrorText
                message={t(`transfers.validation.${form.formState.errors.reason.message}`)}
              />
            ) : (
              t('transfers.reviewerReasonHelp')
            )
          }
        />
        {failed && <Alert severity="error">{t('transfers.actionError')}</Alert>}
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
          <Button
            variant="contained"
            disabled={pending}
            onClick={() =>
              void act(
                stage === 'advisor'
                  ? 'recommend'
                  : stage === 'departmentHead'
                    ? 'verify'
                    : 'approve',
              )
            }
          >
            {t(
              `transfers.review.${stage === 'advisor' ? 'recommend' : stage === 'departmentHead' ? 'verify' : 'approve'}`,
            )}
          </Button>
          <Button disabled={pending} onClick={() => void act('return')}>
            {t('transfers.review.return')}
          </Button>
          <Button
            color="error"
            variant="contained"
            startIcon={<BlockIcon />}
            disabled={pending}
            onClick={async () => {
              if (await form.trigger('reason')) {
                if (form.getValues('reason').trim()) setConfirmReject(true)
                else form.setError('reason', { message: 'required' })
              }
            }}
          >
            {t('transfers.review.reject')}
          </Button>
        </Stack>
      </Stack>
      <Dialog
        open={confirmReject}
        onClose={() => setConfirmReject(false)}
        aria-labelledby={`transfer-reject-${id}`}
      >
        <DialogTitle id={`transfer-reject-${id}`}>{t('transfers.review.reject')}</DialogTitle>
        <DialogContent>
          <DialogContentText>
            <Trans
              t={t}
              i18nKey="transfers.rejectConfirm"
              values={{ course: row.target_course_code }}
              components={{ code: <bdi dir="ltr" /> }}
            />
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirmReject(false)}>{t('transfers.cancel')}</Button>
          <Button
            color="error"
            variant="contained"
            onClick={() => {
              setConfirmReject(false)
              void act('reject')
            }}
          >
            {t('transfers.confirmReject')}
          </Button>
        </DialogActions>
      </Dialog>
    </Paper>
  )
}
