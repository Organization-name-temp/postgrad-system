import { useEffect, useState } from 'react'
import {
  Alert,
  AlertTitle,
  Box,
  Button,
  CircularProgress,
  Divider,
  MenuItem,
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
  getDeanDecisionsQueueQueryKey,
  useAdvisorsForTheDecisionForm,
  useRecordTheFinalDecision,
  useReviewAnApplication,
  useStatusTimeline,
} from '../../../shared/api/generated/endpoints'
import type { RecordTheFinalDecision200 } from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { ApplicationStatusChip } from '../ApplicationStatusChip'
import { StatusTimeline } from '../StatusTimeline'

/** A held decision awaiting an input before it is confirmed (accept needs only the advisor). */
type PendingDecision = 'conditional' | 'waitlist' | 'reject' | null

/** The minted University ID from an `accept`, if the response carried one. */
function mintedUniversityId(result: RecordTheFinalDecision200): string | undefined {
  return 'meta' in result ? result.meta?.university_id : undefined
}

/**
 * The Dean's final-decision screen (ticket 12) — the frontend for the acceptance mint
 * ticket 10 built server-side. A Dean opens an application that has finished its
 * interview (`interview_completed`) or is holding a `conditionally_accepted` /
 * `waitlisted` offer, and records the outcome: **accept & admit** (with the pre-filled,
 * overridable least-loaded advisor — which mints the Student and shows the University
 * ID), **conditional offer**, **waitlist**, or **reject**. The held offers only accept
 * or reject (a resolution). Faculty scope, legal transition, and advisor validity are
 * enforced by the API — an out-of-faculty application is a 403, a wrong-status one a
 * 409, a non-advisor a 422 — surfaced here as an inline alert. Gated on `applicant.decide`.
 */
export function DeanDecisionPage() {
  const { t, i18n } = useTranslation('admissions')
  const { applicationId } = useParams()
  const id = Number(applicationId)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const detail = useReviewAnApplication(id)
  const timeline = useStatusTimeline(id)
  const advisors = useAdvisorsForTheDecisionForm()
  const decide = useRecordTheFinalDecision()

  const [pending, setPending] = useState<PendingDecision>(null)
  const [advisorId, setAdvisorId] = useState<number | ''>('')
  const [conditions, setConditions] = useState('')
  const [waitlistPosition, setWaitlistPosition] = useState('')
  const [note, setNote] = useState('')
  const [mintedId, setMintedId] = useState<string | null>(null)

  // Pre-fill the advisor with the server's least-loaded suggestion once it arrives,
  // leaving the Dean free to override (spec §Advisor assignment).
  const suggestedAdvisorId = advisors.data?.meta?.suggested_advisor_id
  useEffect(() => {
    if (advisorId === '' && suggestedAdvisorId !== undefined) {
      setAdvisorId(suggestedAdvisorId)
    }
  }, [advisorId, suggestedAdvisorId])

  const application = detail.data?.data
  const advisorOptions = advisors.data?.data ?? []
  const actionError = decide.isError
    ? (apiErrorMessage(decide.error) ?? t('decisions.actionError'))
    : undefined

  const backButton = (
    <Button
      component={RouterLink}
      to="/dean/decisions"
      startIcon={<ArrowBackIcon />}
      size="small"
      sx={{ mb: 2 }}
    >
      {t('decisions.backToQueue')}
    </Button>
  )

  if (detail.isPending) {
    return (
      <Box sx={{ py: 4 }}>
        {backButton}
        <CircularProgress aria-label={t('decisions.detailLoading')} />
      </Box>
    )
  }

  if (detail.isError || !application) {
    return (
      <Box sx={{ py: 2 }}>
        {backButton}
        <Alert severity="error">{t('decisions.detailLoadError')}</Alert>
      </Box>
    )
  }

  const applicantName = pickLocaleName(application, i18n.language, application.national_id ?? '')
  const program = application.primary_program
  const programName = program ? pickLocaleName(program, i18n.language, program.code ?? '—') : '—'
  const status = application.status
  const isHeldOffer = status === 'conditionally_accepted' || status === 'waitlisted'

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: getDeanDecisionsQueueQueryKey() })
    void detail.refetch()
    void timeline.refetch()
  }

  const record = (action: 'accept' | 'conditional' | 'waitlist' | 'reject') => {
    decide.mutate(
      {
        applicationId: id,
        data: {
          action,
          advisor_id: action === 'accept' && advisorId !== '' ? advisorId : null,
          conditions: action === 'conditional' ? conditions : null,
          waitlist_position: action === 'waitlist' ? Number(waitlistPosition) : null,
          note: action === 'reject' ? note : null,
        },
      },
      {
        onSuccess: (result) => {
          if (action === 'accept') {
            setMintedId(mintedUniversityId(result) ?? null)
            refresh()
          } else {
            refresh()
            void navigate('/dean/decisions')
          }
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

  // Once admitted, the decision is done: show the minted University ID and stop offering
  // further actions (the application has left the queue).
  const admitted = mintedId !== null

  return (
    <Box sx={{ py: 2 }}>
      {backButton}

      <Stack direction="row" spacing={2} sx={{ alignItems: 'center', mb: 2, flexWrap: 'wrap' }}>
        <Typography variant="h4" component="h1">
          {applicantName}
        </Typography>
        <ApplicationStatusChip status={status} />
      </Stack>

      <Stack spacing={2}>
        <Paper variant="outlined" sx={{ p: 2 }}>
          <Divider textAlign="left" sx={{ mb: 1 }}>
            {t('screening.sections.applicant')}
          </Divider>
          <Stack spacing={0.5}>
            {row(t('personalInfo.fields.national_id'), application.national_id ?? '')}
            {row(t('program.primaryLabel'), programName)}
            {row(
              t('personalInfo.fields.applying_for_degree'),
              application.applying_for_degree ?? '',
            )}
          </Stack>
          {isHeldOffer && (
            <Alert severity="info" sx={{ mt: 2 }}>
              {t('decisions.held')}
              {status === 'conditionally_accepted' && application.conditions
                ? ` — ${t('decisions.conditionsShown')}: ${application.conditions}`
                : ''}
              {status === 'waitlisted' && application.waitlist_position != null
                ? ` — ${t('decisions.positionShown')}: ${application.waitlist_position}`
                : ''}
            </Alert>
          )}
        </Paper>

        <Paper variant="outlined" sx={{ p: 2 }}>
          <StatusTimeline
            entries={timeline.data?.data ?? []}
            currentStatus={status}
            isPending={timeline.isPending}
            isError={timeline.isError}
          />
        </Paper>

        <Paper variant="outlined" sx={{ p: 2 }}>
          <Divider textAlign="left" sx={{ mb: 1 }}>
            {t('decisions.decide')}
          </Divider>

          {admitted && (
            <Alert severity="success" sx={{ mb: 2 }}>
              <AlertTitle>{t('decisions.acceptedTitle')}</AlertTitle>
              {t('decisions.acceptedBody', { universityId: mintedId })}
            </Alert>
          )}
          {actionError && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {actionError}
            </Alert>
          )}
          {advisors.isError && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {t('decisions.advisorsError')}
            </Alert>
          )}

          {!admitted && (
            <>
              <TextField
                select
                label={t('decisions.advisor')}
                helperText={
                  advisorOptions.length === 0
                    ? t('decisions.noAdvisors')
                    : t('decisions.advisorHelp')
                }
                value={advisorId === '' ? '' : String(advisorId)}
                onChange={(event) => setAdvisorId(Number(event.target.value))}
                fullWidth
                disabled={advisorOptions.length === 0}
                sx={{ mb: 2, maxWidth: 480 }}
              >
                {advisorOptions.map((advisor) => (
                  <MenuItem key={advisor.id} value={String(advisor.id)}>
                    {advisor.name} · {advisor.email}
                  </MenuItem>
                ))}
              </TextField>

              {pending !== null && (
                <TextField
                  label={
                    pending === 'conditional'
                      ? t('decisions.conditionsLabel')
                      : pending === 'waitlist'
                        ? t('decisions.positionLabel')
                        : t('decisions.noteLabel')
                  }
                  helperText={
                    pending === 'conditional'
                      ? t('decisions.conditionsHelp')
                      : pending === 'reject'
                        ? t('decisions.noteHelp')
                        : undefined
                  }
                  type={pending === 'waitlist' ? 'number' : 'text'}
                  value={
                    pending === 'conditional'
                      ? conditions
                      : pending === 'waitlist'
                        ? waitlistPosition
                        : note
                  }
                  onChange={(event) => {
                    if (pending === 'conditional') setConditions(event.target.value)
                    else if (pending === 'waitlist') setWaitlistPosition(event.target.value)
                    else setNote(event.target.value)
                  }}
                  multiline={pending !== 'waitlist'}
                  minRows={pending !== 'waitlist' ? 3 : undefined}
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
                      onClick={() => record('accept')}
                      // Require an advisor (the suggestion pre-fills it) so accept never
                      // posts a null advisor; disabled too while no advisor exists at all.
                      disabled={decide.isPending || advisorId === ''}
                    >
                      {t('decisions.actions.accept')}
                    </Button>
                    {status === 'interview_completed' && (
                      <>
                        <Button variant="outlined" onClick={() => setPending('conditional')}>
                          {t('decisions.actions.conditional')}
                        </Button>
                        <Button variant="outlined" onClick={() => setPending('waitlist')}>
                          {t('decisions.actions.waitlist')}
                        </Button>
                      </>
                    )}
                    <Button variant="outlined" color="error" onClick={() => setPending('reject')}>
                      {t('decisions.actions.reject')}
                    </Button>
                  </>
                ) : (
                  <>
                    <Button
                      variant="contained"
                      color={pending === 'reject' ? 'error' : 'primary'}
                      onClick={() => record(pending)}
                      disabled={
                        decide.isPending ||
                        (pending === 'conditional' && conditions.trim() === '') ||
                        (pending === 'waitlist' && waitlistPosition.trim() === '') ||
                        (pending === 'reject' && note.trim() === '')
                      }
                    >
                      {decide.isPending ? t('decisions.working') : t('decisions.confirm')}
                    </Button>
                    <Button
                      variant="text"
                      onClick={() => {
                        setPending(null)
                        setConditions('')
                        setWaitlistPosition('')
                        setNote('')
                      }}
                      disabled={decide.isPending}
                    >
                      {t('decisions.cancel')}
                    </Button>
                  </>
                )}
              </Stack>
            </>
          )}

          {admitted && (
            <Button component={RouterLink} to="/dean/decisions" variant="outlined">
              {t('decisions.backToQueue')}
            </Button>
          )}
        </Paper>
      </Stack>
    </Box>
  )
}
