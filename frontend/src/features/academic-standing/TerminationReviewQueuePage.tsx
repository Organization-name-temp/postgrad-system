import { useEffect, useState } from 'react'
import {
  Alert,
  Button,
  Chip,
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
  Pagination,
} from '@mui/material'
import BlockIcon from '@mui/icons-material/Block'
import DownloadIcon from '@mui/icons-material/Download'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import {
  useGetApiV1TerminationCasesAdvisor,
  useGetApiV1TerminationCasesCouncil,
  useGetApiV1TerminationCasesDepartmentHead,
  usePostApiV1TerminationCasesCaseIdAdvisorReview,
  usePostApiV1TerminationCasesCaseIdCouncilDecision,
  usePostApiV1TerminationCasesCaseIdDepartmentReview,
  getApiV1TerminationCasesAdvisorExport,
  getApiV1TerminationCasesDepartmentHeadExport,
  getApiV1TerminationCasesCouncilExport,
} from '../../shared/api/generated/endpoints'
import { downloadBlob } from '../enrollment/credit-decisions/downloadBlob'

type Stage = 'advisor' | 'departmentHead' | 'council'
type CaseRow = {
  id?: number
  student_id?: number
  fingerprint?: string
  state?: string
  evidence?: Record<string, unknown>
  advisor_assessment?: string | null
  department_head_reason?: string | null
  council_reason?: string | null
  closed_reason?: string | null
}

const reviewSchema = z.object({ reason: z.string().trim().min(1).max(4000) })
type ReviewValues = z.infer<typeof reviewSchema>

export function TerminationReviewQueuePage({ stage }: { stage: Stage }) {
  const { t } = useTranslation('academicStanding')
  const [page, setPage] = useState(1)
  const params = { page, include_decided: stage === 'council' ? true : undefined }
  const advisor = useGetApiV1TerminationCasesAdvisor(params, {
    query: { enabled: stage === 'advisor' },
  })
  const head = useGetApiV1TerminationCasesDepartmentHead(params, {
    query: { enabled: stage === 'departmentHead' },
  })
  const council = useGetApiV1TerminationCasesCouncil(params, {
    query: { enabled: stage === 'council' },
  })
  const query = stage === 'advisor' ? advisor : stage === 'departmentHead' ? head : council
  const rows = (query.data?.data ?? []) as CaseRow[]
  const lastPage = Math.max(1, query.data?.meta?.last_page ?? 1)
  useEffect(() => {
    if (!query.isPending && page > lastPage) setPage(lastPage)
  }, [lastPage, page, query.isPending])
  const download = async () => {
    const blob =
      stage === 'advisor'
        ? await getApiV1TerminationCasesAdvisorExport()
        : stage === 'departmentHead'
          ? await getApiV1TerminationCasesDepartmentHeadExport()
          : await getApiV1TerminationCasesCouncilExport()
    downloadBlob(blob, `${stage}-termination-cases.xlsx`)
  }

  return (
    <Stack spacing={3}>
      <div>
        <Typography variant="h4" component="h1">
          {t(`termination.review.${stage}.title`)}
        </Typography>
        <Typography color="text.secondary">{t('termination.review.intro')}</Typography>
      </div>
      <Button
        startIcon={<DownloadIcon />}
        variant="outlined"
        onClick={() => void download()}
        sx={{ alignSelf: 'flex-start' }}
      >
        {t('termination.review.export')}
      </Button>
      {query.isError && <Alert severity="error">{t('termination.review.loadError')}</Alert>}
      {query.isPending && (
        <Stack spacing={2} aria-label={t('termination.review.loading')}>
          <Skeleton variant="rounded" height={260} />
          <Skeleton variant="rounded" height={260} />
        </Stack>
      )}
      {!query.isPending && !query.isError && rows.length === 0 && (
        <Alert severity="info">{t('termination.review.empty')}</Alert>
      )}
      {rows.map((row) => (
        <TerminationCaseCard
          key={row.id}
          row={row}
          stage={stage}
          onChanged={() => query.refetch()}
        />
      ))}
      {lastPage > 1 && (
        <Pagination
          count={lastPage}
          page={page}
          onChange={(_, next) => setPage(next)}
          sx={{ alignSelf: 'center' }}
        />
      )}
    </Stack>
  )
}

function TerminationCaseCard({
  row,
  stage,
  onChanged,
}: {
  row: CaseRow
  stage: Stage
  onChanged: () => Promise<unknown>
}) {
  const { t } = useTranslation('academicStanding')
  const [confirmTerminate, setConfirmTerminate] = useState(false)
  const form = useForm<ReviewValues>({
    resolver: zodResolver(reviewSchema),
    defaultValues: { reason: '' },
  })
  const advisor = usePostApiV1TerminationCasesCaseIdAdvisorReview()
  const head = usePostApiV1TerminationCasesCaseIdDepartmentReview()
  const council = usePostApiV1TerminationCasesCaseIdCouncilDecision()
  const pending = advisor.isPending || head.isPending || council.isPending
  const decide = async (action: string) => {
    if (!row.id || !(await form.trigger())) return
    const reason = form.getValues('reason').trim()
    if (stage === 'advisor') {
      await advisor.mutateAsync({
        caseId: row.id,
        data: { assessment: reason, recommendation: action },
      })
    } else if (stage === 'departmentHead') {
      await head.mutateAsync({ caseId: row.id, data: { action, reason } })
    } else {
      await council.mutateAsync({ caseId: row.id, data: { action, reason } })
    }
    form.reset()
    await onChanged()
  }
  const positive =
    stage === 'advisor' ? 'continue' : stage === 'departmentHead' ? 'recommend' : 'terminate'
  const actionable =
    row.state ===
    (stage === 'advisor'
      ? 'AwaitingAdvisor'
      : stage === 'departmentHead'
        ? 'AwaitingDepartmentHead'
        : 'AwaitingCouncil')

  return (
    <Paper
      variant="outlined"
      sx={(theme) => ({ p: { xs: 2, sm: 3 }, borderRadius: theme.shape.radiusLg })}
    >
      <Stack spacing={2}>
        <Stack direction="row" sx={{ justifyContent: 'space-between', gap: 1 }}>
          <Typography variant="h6" dir="ltr">
            {t('termination.review.student', { id: row.student_id })}
          </Typography>
          <Chip label={t(`termination.states.${row.state}`)} />
        </Stack>
        <Typography variant="body2" dir="ltr" sx={{ overflowWrap: 'anywhere' }}>
          {t('termination.review.fingerprint', { value: row.fingerprint })}
        </Typography>
        <Stack spacing={0.5}>
          <Typography variant="subtitle2">{t('termination.review.evidenceTitle')}</Typography>
          {Object.entries(row.evidence ?? {}).map(([key, value]) => (
            <Typography key={key} variant="body2">
              {t(`termination.review.evidence.${key}`)}: {Array.isArray(value) ? value.length : 1}
            </Typography>
          ))}
        </Stack>
        {row.advisor_assessment && <Typography>{row.advisor_assessment}</Typography>}
        {row.department_head_reason && <Typography>{row.department_head_reason}</Typography>}
        {row.council_reason && <Typography>{row.council_reason}</Typography>}
        {actionable && (
          <TextField
            label={t('termination.review.reason')}
            {...form.register('reason')}
            error={Boolean(form.formState.errors.reason)}
            helperText={form.formState.errors.reason ? t('termination.review.required') : undefined}
            multiline
            minRows={2}
            required
          />
        )}
        {actionable && (
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
            <Button
              variant="contained"
              disabled={pending}
              onClick={() => {
                if (positive === 'terminate') setConfirmTerminate(true)
                else void decide(positive)
              }}
            >
              {t(`termination.review.actions.${positive}`)}
            </Button>
            <Button color="error" disabled={pending} onClick={() => decide('dismiss')}>
              {t('termination.review.actions.dismiss')}
            </Button>
          </Stack>
        )}
      </Stack>
      <Dialog open={confirmTerminate} onClose={() => setConfirmTerminate(false)}>
        <DialogTitle>{t('termination.review.actions.terminate')}</DialogTitle>
        <DialogContent>
          <DialogContentText>
            {t('termination.review.terminateConfirm', { id: row.student_id })}
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirmTerminate(false)}>
            {t('termination.review.cancel')}
          </Button>
          <Button
            color="error"
            variant="contained"
            startIcon={<BlockIcon />}
            onClick={() => {
              setConfirmTerminate(false)
              void decide('terminate')
            }}
          >
            {t('termination.review.actions.terminate')}
          </Button>
        </DialogActions>
      </Dialog>
    </Paper>
  )
}
