import { useState } from 'react'
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
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import {
  useGetApiV1TerminationCasesAdvisor,
  useGetApiV1TerminationCasesCouncil,
  useGetApiV1TerminationCasesDepartmentHead,
  usePostApiV1TerminationCasesCaseIdAdvisorReview,
  usePostApiV1TerminationCasesCaseIdCouncilDecision,
  usePostApiV1TerminationCasesCaseIdDepartmentReview,
} from '../../shared/api/generated/endpoints'

type Stage = 'advisor' | 'departmentHead' | 'council'
type CaseRow = {
  id?: number
  student_id?: number
  fingerprint?: string
  state?: string
  evidence?: Record<string, unknown>
  advisor_assessment?: string | null
  department_head_reason?: string | null
}

export function TerminationReviewQueuePage({ stage }: { stage: Stage }) {
  const { t } = useTranslation('academicStanding')
  const advisor = useGetApiV1TerminationCasesAdvisor({ query: { enabled: stage === 'advisor' } })
  const head = useGetApiV1TerminationCasesDepartmentHead({
    query: { enabled: stage === 'departmentHead' },
  })
  const council = useGetApiV1TerminationCasesCouncil({
    query: { enabled: stage === 'council' },
  })
  const query = stage === 'advisor' ? advisor : stage === 'departmentHead' ? head : council
  const rows = (query.data?.data ?? []) as CaseRow[]

  return (
    <Stack spacing={3}>
      <div>
        <Typography variant="h4" component="h1">
          {t(`termination.review.${stage}.title`)}
        </Typography>
        <Typography color="text.secondary">{t('termination.review.intro')}</Typography>
      </div>
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
  const [reason, setReason] = useState('')
  const [confirmTerminate, setConfirmTerminate] = useState(false)
  const advisor = usePostApiV1TerminationCasesCaseIdAdvisorReview()
  const head = usePostApiV1TerminationCasesCaseIdDepartmentReview()
  const council = usePostApiV1TerminationCasesCaseIdCouncilDecision()
  const pending = advisor.isPending || head.isPending || council.isPending
  const decide = async (action: string) => {
    if (!row.id || !reason.trim()) return
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
    setReason('')
    await onChanged()
  }
  const positive =
    stage === 'advisor' ? 'continue' : stage === 'departmentHead' ? 'recommend' : 'terminate'

  return (
    <Paper
      variant="outlined"
      sx={(theme) => ({ p: { xs: 2, sm: 3 }, borderRadius: theme.shape.radiusLg })}
    >
      <Stack spacing={2}>
        <Stack direction="row" sx={{ justifyContent: 'space-between', gap: 1 }}>
          <Typography variant="h6">
            {t('termination.review.student', { id: row.student_id })}
          </Typography>
          <Chip label={t(`termination.states.${row.state}`)} />
        </Stack>
        <Typography variant="body2" sx={{ overflowWrap: 'anywhere' }}>
          {t('termination.review.fingerprint', { value: row.fingerprint })}
        </Typography>
        <Typography component="pre" variant="body2" sx={{ whiteSpace: 'pre-wrap' }}>
          {JSON.stringify(row.evidence ?? {}, null, 2)}
        </Typography>
        {row.advisor_assessment && <Typography>{row.advisor_assessment}</Typography>}
        {row.department_head_reason && <Typography>{row.department_head_reason}</Typography>}
        <TextField
          label={t('termination.review.reason')}
          value={reason}
          onChange={(event) => setReason(event.target.value)}
          multiline
          minRows={2}
          required
        />
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
          <Button
            variant="contained"
            disabled={pending || !reason.trim()}
            onClick={() => {
              if (positive === 'terminate') setConfirmTerminate(true)
              else void decide(positive)
            }}
          >
            {t(`termination.review.actions.${positive}`)}
          </Button>
          <Button
            color="error"
            disabled={pending || !reason.trim()}
            onClick={() => decide('dismiss')}
          >
            {t('termination.review.actions.dismiss')}
          </Button>
        </Stack>
      </Stack>
      <Dialog open={confirmTerminate} onClose={() => setConfirmTerminate(false)}>
        <DialogTitle>{t('termination.review.actions.terminate')}</DialogTitle>
        <DialogContent>
          <DialogContentText>{t('termination.review.terminateConfirm')}</DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirmTerminate(false)}>
            {t('termination.review.cancel')}
          </Button>
          <Button
            color="error"
            variant="contained"
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
