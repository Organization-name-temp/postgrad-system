import { useEffect, useState } from 'react'
import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Stack,
  TextField,
} from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getInterviewsAssignedToMeQueryKey,
  useRecordMyEvaluation,
} from '../../../shared/api/generated/endpoints'
import type { InterviewsAssignedToMe200DataItem } from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'

interface EvaluationDialogProps {
  open: boolean
  onClose: () => void
  interview: InterviewsAssignedToMe200DataItem | undefined
}

/**
 * The interviewer's evaluation form (features/admissions): a score (1–10) and optional
 * notes for the interview they conducted. Only the assigned interviewer may save — the
 * API enforces the row-level rule and returns 403 for anyone else, surfaced inline. On
 * success the assigned-interviews list is invalidated so the recorded score shows.
 */
export function EvaluationDialog({ open, onClose, interview }: EvaluationDialogProps) {
  const { t } = useTranslation('admissions')
  const queryClient = useQueryClient()

  const [score, setScore] = useState('')
  const [notes, setNotes] = useState('')

  useEffect(() => {
    if (open) {
      setScore(interview?.evaluation_score !== undefined ? String(interview.evaluation_score) : '')
      setNotes(interview?.evaluation_notes ?? '')
    }
  }, [open, interview])

  const record = useRecordMyEvaluation()
  const error = record.isError ? apiErrorMessage(record.error) : undefined

  const handleSubmit = () => {
    if (interview?.id === undefined || score === '') {
      return
    }
    record.mutate(
      { interviewId: interview.id, data: { score: Number(score), notes: notes || null } },
      {
        onSuccess: () => {
          void queryClient.invalidateQueries({ queryKey: getInterviewsAssignedToMeQueryKey() })
          onClose()
        },
      },
    )
  }

  const scoreValue = Number(score)
  const scoreValid =
    score !== '' && Number.isInteger(scoreValue) && scoreValue >= 1 && scoreValue <= 10

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle>{t('interviews.evaluation.title')}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ mt: 1 }}>
          {error && <Alert severity="error">{error}</Alert>}
          <TextField
            label={t('interviews.evaluation.score')}
            type="number"
            value={score}
            onChange={(event) => setScore(event.target.value)}
            required
            fullWidth
            helperText={t('interviews.evaluation.scoreHelp')}
            slotProps={{ htmlInput: { min: 1, max: 10 } }}
          />
          <TextField
            label={t('interviews.evaluation.notes')}
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
            multiline
            minRows={3}
            fullWidth
          />
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={record.isPending}>
          {t('interviews.cancel')}
        </Button>
        <Button
          onClick={handleSubmit}
          variant="contained"
          disabled={record.isPending || !scoreValid}
        >
          {t('interviews.save')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
