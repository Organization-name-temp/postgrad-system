import { useState } from 'react'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Divider,
  Link,
  List,
  ListItem,
  ListItemText,
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
  getScreeningQueueQueryKey,
  useReviewAnApplication,
  useScreenAnApplication,
  useStatusTimeline,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { openDocument } from '../openDocument'
import { ApplicationStatusChip } from '../ApplicationStatusChip'
import { StatusTimeline } from '../StatusTimeline'

/** The action awaiting a note before it is confirmed (approve needs none). */
type PendingAction = 'return' | 'reject' | null

/**
 * The Student Affairs application review screen (ticket 07). A reviewer opens a
 * queued application, reads the applicant's details and documents alongside the
 * status timeline, and records a screening decision: **approve** (auto-forwarded to
 * the Dean), **return for revision**, or **reject**. Return and reject require a note
 * that is shared with the applicant. Gated on `application.screen` (route + API).
 */
export function ApplicationReviewPage() {
  const { t, i18n } = useTranslation('admissions')
  const { applicationId } = useParams()
  const id = Number(applicationId)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const detail = useReviewAnApplication(id)
  const timeline = useStatusTimeline(id)
  const screen = useScreenAnApplication()

  const [pending, setPending] = useState<PendingAction>(null)
  const [note, setNote] = useState('')

  const application = detail.data?.data
  const actionError = screen.isError
    ? (apiErrorMessage(screen.error) ?? t('screening.actionError'))
    : undefined

  const backButton = (
    <Button
      component={RouterLink}
      to="/screening"
      startIcon={<ArrowBackIcon />}
      size="small"
      sx={{ mb: 2 }}
    >
      {t('screening.backToQueue')}
    </Button>
  )

  if (detail.isPending) {
    return (
      <Box sx={{ py: 4 }}>
        {backButton}
        <CircularProgress aria-label={t('screening.detailLoading')} />
      </Box>
    )
  }

  if (detail.isError || !application) {
    return (
      <Box sx={{ py: 2 }}>
        {backButton}
        <Alert severity="error">{t('screening.detailLoadError')}</Alert>
      </Box>
    )
  }

  const applicantName = pickLocaleName(application, i18n.language, application.national_id ?? '')
  const program = application.primary_program
  const programName = program ? pickLocaleName(program, i18n.language, program.code ?? '—') : '—'
  const documents = application.documents ?? []

  const submit = (action: 'approve' | 'return' | 'reject') => {
    screen.mutate(
      { applicationId: id, data: { action, note: action === 'approve' ? undefined : note } },
      {
        onSuccess: () => {
          void queryClient.invalidateQueries({ queryKey: getScreeningQueueQueryKey() })
          void navigate('/screening')
        },
      },
    )
  }

  const row = (label: string, value: string) => (
    <Stack direction="row" spacing={1} sx={{ justifyContent: 'space-between' }}>
      <Typography variant="body2" color="text.secondary">
        {label}
      </Typography>
      <Typography variant="body2" sx={{ fontWeight: 500, textAlign: 'end' }}>
        {value}
      </Typography>
    </Stack>
  )

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
        <Paper variant="outlined" sx={{ p: 2 }}>
          <Divider textAlign="left" sx={{ mb: 1 }}>
            {t('screening.sections.applicant')}
          </Divider>
          <Stack spacing={0.5}>
            {row(t('personalInfo.fields.national_id'), application.national_id ?? '')}
            {row(t('personalInfo.fields.nationality'), application.nationality ?? '')}
            {row(t('personalInfo.fields.previous_degree'), application.previous_degree ?? '')}
            {row(
              t('personalInfo.fields.previous_university'),
              application.previous_university ?? '',
            )}
            {row(t('personalInfo.fields.graduation_gpa'), application.graduation_gpa ?? '')}
            {row(t('personalInfo.fields.english_test'), application.english_test ?? '')}
          </Stack>
        </Paper>

        <Paper variant="outlined" sx={{ p: 2 }}>
          <Divider textAlign="left" sx={{ mb: 1 }}>
            {t('screening.sections.program')}
          </Divider>
          <Stack spacing={0.5}>
            {row(t('program.primaryLabel'), programName)}
            {row(
              t('personalInfo.fields.applying_for_degree'),
              application.applying_for_degree ?? '',
            )}
          </Stack>
        </Paper>

        <Paper variant="outlined" sx={{ p: 2 }}>
          <Divider textAlign="left" sx={{ mb: 1 }}>
            {t('screening.sections.documents')}
          </Divider>
          <Typography variant="body2" gutterBottom>
            {t('screening.documentsUploaded', { count: documents.length })}
          </Typography>
          <List dense disablePadding>
            {documents.map((document) => (
              <ListItem key={document.id} disableGutters>
                <ListItemText
                  primary={
                    document.download_url ? (
                      <Link
                        component="button"
                        type="button"
                        onClick={() => {
                          if (document.download_url) void openDocument(document.download_url)
                        }}
                        sx={{ textAlign: 'start' }}
                      >
                        {document.original_filename}
                      </Link>
                    ) : (
                      document.original_filename
                    )
                  }
                  secondary={t(`documents.types.${document.document_type}`)}
                />
              </ListItem>
            ))}
          </List>
        </Paper>

        <Paper variant="outlined" sx={{ p: 2 }}>
          <StatusTimeline
            entries={timeline.data?.data ?? []}
            currentStatus={application.status}
            isPending={timeline.isPending}
            isError={timeline.isError}
          />
        </Paper>

        <Paper variant="outlined" sx={{ p: 2 }}>
          <Divider textAlign="left" sx={{ mb: 1 }}>
            {t('screening.decide')}
          </Divider>

          {actionError && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {actionError}
            </Alert>
          )}

          {pending !== null && (
            <TextField
              label={t('screening.noteLabel')}
              helperText={t('screening.noteHelp')}
              value={note}
              onChange={(event) => setNote(event.target.value)}
              multiline
              minRows={3}
              fullWidth
              sx={{ mb: 2 }}
            />
          )}

          <Stack direction="row" spacing={2} sx={{ flexWrap: 'wrap' }}>
            {pending === null ? (
              <>
                <Button
                  variant="contained"
                  color="success"
                  onClick={() => submit('approve')}
                  disabled={screen.isPending}
                >
                  {t('screening.actions.approve')}
                </Button>
                <Button variant="outlined" color="warning" onClick={() => setPending('return')}>
                  {t('screening.actions.return')}
                </Button>
                <Button variant="outlined" color="error" onClick={() => setPending('reject')}>
                  {t('screening.actions.reject')}
                </Button>
              </>
            ) : (
              <>
                <Button
                  variant="contained"
                  color={pending === 'reject' ? 'error' : 'warning'}
                  onClick={() => submit(pending)}
                  disabled={screen.isPending || note.trim() === ''}
                >
                  {screen.isPending ? t('screening.working') : t('screening.confirm')}
                </Button>
                <Button
                  variant="text"
                  onClick={() => {
                    setPending(null)
                    setNote('')
                  }}
                  disabled={screen.isPending}
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
