import { useState } from 'react'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Divider,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import { useQueryClient } from '@tanstack/react-query'
import { Link as RouterLink, useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  getDeanReviewQueueQueryKey,
  useDeanDecisionOnAnApplication,
  useReviewAnApplication,
  useStatusTimeline,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { ApplicationRecordDetail } from '../ApplicationRecordDetail'
import { ApplicationStatusChip } from '../ApplicationStatusChip'

/**
 * The Dean review screen (ticket 08). A Dean opens one of their faculty's approved
 * applications, reads the applicant's details and documents alongside the status
 * timeline, and records a decision: **approve for interview** or **reject** (with a
 * reason shared with the applicant). Both notify the applicant. The faculty scope and
 * the legal-transition check are enforced by the API — an out-of-faculty application
 * is a 403, a wrong-status one a 409 — surfaced here as an inline alert.
 */
export function DeanReviewPage() {
  const { t, i18n } = useTranslation('admissions')
  const { applicationId } = useParams()
  const id = Number(applicationId)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const detail = useReviewAnApplication(id)
  const timeline = useStatusTimeline(id)
  const decide = useDeanDecisionOnAnApplication()

  const [rejecting, setRejecting] = useState(false)
  const [note, setNote] = useState('')

  const application = detail.data?.data
  const actionError = decide.isError
    ? (apiErrorMessage(decide.error) ?? t('deanReview.actionError'))
    : undefined

  const backButton = (
    <Button
      component={RouterLink}
      to="/dean"
      startIcon={<ArrowBackIcon />}
      size="small"
      sx={{ mb: 2 }}
    >
      {t('deanReview.backToQueue')}
    </Button>
  )

  if (detail.isPending) {
    return (
      <Box sx={{ py: 4 }}>
        {backButton}
        <CircularProgress aria-label={t('deanReview.detailLoading')} />
      </Box>
    )
  }

  if (detail.isError || !application) {
    return (
      <Box sx={{ py: 2 }}>
        {backButton}
        <Alert severity="error">{t('deanReview.detailLoadError')}</Alert>
      </Box>
    )
  }

  const applicantName = pickLocaleName(application, i18n.language, application.national_id ?? '')

  const submit = (action: 'approve' | 'reject') => {
    decide.mutate(
      { applicationId: id, data: { action, note: action === 'approve' ? undefined : note } },
      {
        onSuccess: () => {
          void queryClient.invalidateQueries({ queryKey: getDeanReviewQueueQueryKey() })
          void navigate('/dean')
        },
      },
    )
  }

  return (
    <Box sx={{ py: 2 }}>
      {backButton}

      <Stack direction="row" spacing={2} sx={{ alignItems: 'center', mb: 2, flexWrap: 'wrap' }}>
        <Typography variant="h4" component="h1">
          {applicantName}
        </Typography>
        <ApplicationStatusChip status={application.status} />
      </Stack>

      <Stack spacing={2}>
        <ApplicationRecordDetail
          application={application}
          timeline={{
            entries: timeline.data?.data ?? [],
            isPending: timeline.isPending,
            isError: timeline.isError,
          }}
        />

        <Paper variant="outlined" sx={{ p: 2 }}>
          <Divider textAlign="left" sx={{ mb: 1 }}>
            {t('deanReview.decide')}
          </Divider>

          {actionError && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {actionError}
            </Alert>
          )}

          {rejecting && (
            <TextField
              label={t('deanReview.noteLabel')}
              helperText={t('deanReview.noteHelp')}
              value={note}
              onChange={(event) => setNote(event.target.value)}
              multiline
              minRows={3}
              fullWidth
              sx={{ mb: 2 }}
            />
          )}

          <Stack direction="row" spacing={2} sx={{ flexWrap: 'wrap' }}>
            {!rejecting ? (
              <>
                <Button
                  variant="contained"
                  color="success"
                  onClick={() => submit('approve')}
                  disabled={decide.isPending}
                >
                  {t('deanReview.actions.approve')}
                </Button>
                <Button variant="outlined" color="error" onClick={() => setRejecting(true)}>
                  {t('deanReview.actions.reject')}
                </Button>
              </>
            ) : (
              <>
                <Button
                  variant="contained"
                  color="error"
                  onClick={() => submit('reject')}
                  disabled={decide.isPending || note.trim() === ''}
                >
                  {decide.isPending ? t('deanReview.working') : t('deanReview.confirm')}
                </Button>
                <Button
                  variant="text"
                  onClick={() => {
                    setRejecting(false)
                    setNote('')
                  }}
                  disabled={decide.isPending}
                >
                  {t('screening.cancel')}
                </Button>
              </>
            )}
          </Stack>
        </Paper>
      </Stack>
    </Box>
  )
}
