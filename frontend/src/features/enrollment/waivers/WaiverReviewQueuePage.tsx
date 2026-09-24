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
  Pagination,
  Paper,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import BlockIcon from '@mui/icons-material/Block'
import DownloadIcon from '@mui/icons-material/Download'
import WorkspacePremiumOutlinedIcon from '@mui/icons-material/WorkspacePremiumOutlined'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Trans, useTranslation } from 'react-i18next'
import {
  exportAdvisorWaiverQueue,
  exportCouncilWaiverQueue,
  exportDepartmentHeadWaiverQueue,
  useAcademicAffairsCouncilFinalDecision,
  useAdvisorActionOnAWaiverRequest,
  useAdvisorWaiverQueue,
  useCouncilWaiverQueue,
  useDepartmentHeadWaiverQueue,
  useScopedDepartmentHeadVerification,
} from '../../../shared/api/generated/endpoints'
// Ticket05 will move these shared credit-decision helpers; retarget these imports then.
import { downloadBlob } from '../exemptions/downloadBlob'
import { useRetryStableActionId } from '../exemptions/useRetryStableActionId'
import { headVerificationSchema, waiverReviewSchema, type WaiverReviewValues } from './formSchema'
import type { ReviewerWaiverRequestRow } from './types'
import { WaiverEvidenceSummary } from './WaiverEvidenceSummary'
import { WaiverStatusChip } from './WaiverStatusChip'
import { WaiverTransitionHistory } from './WaiverTransitionHistory'

export type WaiverReviewStage = 'advisor' | 'departmentHead' | 'council'

export function WaiverReviewQueuePage({ stage }: { stage: WaiverReviewStage }) {
  const { t } = useTranslation('enrollment')
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const params = { q: search || undefined, page }
  const advisor = useAdvisorWaiverQueue(params, { query: { enabled: stage === 'advisor' } })
  const head = useDepartmentHeadWaiverQueue(params, {
    query: { enabled: stage === 'departmentHead' },
  })
  const council = useCouncilWaiverQueue(params, { query: { enabled: stage === 'council' } })
  const query = stage === 'advisor' ? advisor : stage === 'departmentHead' ? head : council
  const rows = (query.data?.data ?? []) as ReviewerWaiverRequestRow[]
  const lastPage = Math.max(1, query.data?.meta?.last_page ?? 1)

  useEffect(() => {
    if (!query.isPending && page > lastPage) setPage(lastPage)
  }, [lastPage, page, query.isPending])

  const exportQueue = async () => {
    const blob =
      stage === 'advisor'
        ? await exportAdvisorWaiverQueue(params)
        : stage === 'departmentHead'
          ? await exportDepartmentHeadWaiverQueue(params)
          : await exportCouncilWaiverQueue(params)
    downloadBlob(blob, `waiver-${stage}-queue.xlsx`)
  }

  return (
    <Stack spacing={3}>
      <Box>
        <Typography component="h1" variant="h4">
          {t(`waivers.queue.${stage}.title`)}
        </Typography>
        <Typography color="text.secondary">{t(`waivers.queue.${stage}.intro`)}</Typography>
      </Box>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
        <TextField
          label={t('waivers.search')}
          value={search}
          onChange={(event) => {
            setSearch(event.target.value)
            setPage(1)
          }}
        />
        <Button startIcon={<DownloadIcon />} onClick={() => void exportQueue()}>
          {t('waivers.export')}
        </Button>
      </Stack>
      {query.isPending && (
        <Stack spacing={2} aria-label={t('waivers.loading')}>
          <Skeleton variant="rounded" height={230} />
          <Skeleton variant="rounded" height={230} />
        </Stack>
      )}
      {query.isError && (
        <Alert
          severity="error"
          action={
            <Button color="inherit" onClick={() => void query.refetch()}>
              {t('waivers.retry')}
            </Button>
          }
        >
          {t('waivers.loadError')}
        </Alert>
      )}
      {!query.isPending && !query.isError && rows.length === 0 && (
        <Stack spacing={1} sx={{ alignItems: 'center', py: 4 }}>
          <WorkspacePremiumOutlinedIcon color="disabled" fontSize="large" />
          <Typography color="text.secondary">{t('waivers.queue.empty')}</Typography>
        </Stack>
      )}
      {!query.isPending &&
        !query.isError &&
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
          onChange={(_, next) => setPage(next)}
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
  row: ReviewerWaiverRequestRow
  stage: WaiverReviewStage
  onChanged: () => Promise<unknown>
}) {
  const { t } = useTranslation('enrollment')
  const advisorAction = useAdvisorActionOnAWaiverRequest()
  const headAction = useScopedDepartmentHeadVerification()
  const councilAction = useAcademicAffairsCouncilFinalDecision()
  const actionIds = useRetryStableActionId()
  const [confirmReject, setConfirmReject] = useState(false)
  const form = useForm<WaiverReviewValues>({
    resolver: zodResolver(stage === 'departmentHead' ? headVerificationSchema : waiverReviewSchema),
    mode: 'onBlur',
    defaultValues: { reason: '', coverage: '', equivalencyNote: '' },
  })
  const id = row.id ?? 0
  const revision = row.revision ?? 1
  const pending = advisorAction.isPending || headAction.isPending || councilAction.isPending
  const failed = advisorAction.isError || headAction.isError || councilAction.isError

  const act = async (action: string) => {
    const values = form.getValues()
    if (['return', 'reject'].includes(action) && !values.reason.trim()) {
      form.setError('reason', { message: 'required' })
      return
    }
    if (action === 'verify' && !(await form.trigger(['coverage', 'equivalencyNote']))) return
    if (!(await form.trigger('reason'))) return
    const key = JSON.stringify([stage, id, revision, action, values])
    const shared = {
      action,
      reason: values.reason.trim() || undefined,
      expected_revision: revision,
      action_id: actionIds.forAction(key),
    }
    try {
      if (stage === 'advisor') {
        await advisorAction.mutateAsync({ waiverRequestId: id, data: shared })
      } else if (stage === 'departmentHead') {
        await headAction.mutateAsync({
          waiverRequestId: id,
          data: {
            ...shared,
            coverage_percentage: action === 'verify' ? Number(values.coverage) : undefined,
            equivalency_note: action === 'verify' ? values.equivalencyNote.trim() : undefined,
          },
        })
      } else {
        await councilAction.mutateAsync({ waiverRequestId: id, data: shared })
      }
      actionIds.complete(key)
      form.reset()
      await onChanged()
    } catch {
      // Mutation state renders the retryable error and the id remains stable for a retry.
    }
  }

  const errorText = (message?: string) =>
    message === 'too_long'
      ? t('waivers.tooLong')
      : message === 'coverage_minimum'
        ? t('waivers.coverageMinimum')
        : message
          ? t('waivers.required')
          : undefined

  return (
    <Paper
      variant="outlined"
      sx={(theme) => ({ p: { xs: 2, sm: 3 }, borderRadius: theme.shape.radiusLg })}
    >
      <Stack spacing={2}>
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          sx={{ gap: 1, justifyContent: 'space-between' }}
        >
          <Box>
            <Typography component="h2" variant="h6">
              {row.student?.name}
            </Typography>
            <Typography color="text.secondary">
              <Trans
                t={t}
                i18nKey="waivers.reviewMeta"
                values={{
                  universityId: row.student?.university_id,
                  target: row.target_course_code,
                }}
                components={{ id: <bdi dir="ltr" />, code: <bdi dir="ltr" /> }}
              />
            </Typography>
          </Box>
          <WaiverStatusChip status={row.status} />
        </Stack>
        <WaiverEvidenceSummary
          evidence={row.evidence}
          priorCourseCode={row.prior_course_code}
          priorCourseLevel={row.prior_course_level}
        />
        {row.student_note && <Typography>{row.student_note}</Typography>}
        {row.head_verification && (
          <Alert severity="info">
            <bdi dir="ltr">{row.head_verification.coverage_percentage}%</bdi> —{' '}
            {row.head_verification.equivalency_note}
          </Alert>
        )}
        <WaiverTransitionHistory transitions={row.transitions} />
        {stage === 'departmentHead' && (
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
            <TextField
              {...form.register('coverage')}
              label={t('waivers.coverage')}
              type="number"
              dir="ltr"
              slotProps={{ htmlInput: { min: 80, max: 100, step: 0.01 } }}
              error={Boolean(form.formState.errors.coverage)}
              helperText={
                form.formState.errors.coverage ? (
                  <span role="alert">{errorText(form.formState.errors.coverage.message)}</span>
                ) : undefined
              }
            />
            <TextField
              {...form.register('equivalencyNote')}
              label={t('waivers.equivalencyNote')}
              fullWidth
              error={Boolean(form.formState.errors.equivalencyNote)}
              helperText={
                form.formState.errors.equivalencyNote ? (
                  <span role="alert">
                    {errorText(form.formState.errors.equivalencyNote.message)}
                  </span>
                ) : undefined
              }
            />
          </Stack>
        )}
        <TextField
          {...form.register('reason')}
          label={t('waivers.reviewerReason')}
          multiline
          minRows={2}
          error={Boolean(form.formState.errors.reason)}
          helperText={
            errorText(form.formState.errors.reason?.message) ?? t('waivers.reviewerReasonHelp')
          }
        />
        {failed && <Alert severity="error">{t('waivers.actionError')}</Alert>}
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
          <Button
            variant="contained"
            disabled={pending || (stage === 'departmentHead' && !form.formState.isValid)}
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
            {t(`waivers.review.${stage}`)}
          </Button>
          <Button disabled={pending} onClick={() => void act('return')}>
            {t('waivers.review.return')}
          </Button>
          <Button
            color="error"
            variant="contained"
            startIcon={<BlockIcon />}
            disabled={pending}
            onClick={() => setConfirmReject(true)}
          >
            {t('waivers.review.reject')}
          </Button>
        </Stack>
        <Dialog open={confirmReject} onClose={() => setConfirmReject(false)}>
          <DialogTitle>{t('waivers.review.reject')}</DialogTitle>
          <DialogContent>
            <DialogContentText>{t('waivers.rejectConfirm')}</DialogContentText>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setConfirmReject(false)}>{t('waivers.cancel')}</Button>
            <Button
              color="error"
              variant="contained"
              onClick={() => {
                setConfirmReject(false)
                void act('reject')
              }}
            >
              {t('waivers.confirmReject')}
            </Button>
          </DialogActions>
        </Dialog>
      </Stack>
    </Paper>
  )
}
