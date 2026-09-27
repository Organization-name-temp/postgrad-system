import { useState } from 'react'
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Chip,
  CircularProgress,
  Divider,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import {
  getMyInterviewQueryKey,
  useConfirmMyInterview,
  useMyInterview,
  useOpenSlotsToRescheduleInto,
  useRescheduleMyInterview,
} from '../../../shared/api/generated/endpoints'
import type { OpenSlotsToRescheduleInto200DataItem } from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { formatSlotWhen, slotWhere } from './slotDisplay'

/**
 * The applicant's interview card (features/admissions), shown on the status view once
 * their application reaches `interview_scheduled`. Until Student Affairs books a slot
 * the endpoint 404s and a "coming soon" note is shown; once booked, the applicant sees
 * the when/where/interviewer and can **confirm** the interview or **reschedule** it
 * once into another open slot (the single-reschedule cap is enforced server-side and
 * reflected by hiding the action when `can_reschedule` is false).
 */
export function InterviewPanel() {
  const { t, i18n } = useTranslation('admissions')
  const queryClient = useQueryClient()

  const interviewQuery = useMyInterview({ query: { retry: false } })
  const confirm = useConfirmMyInterview()
  const reschedule = useRescheduleMyInterview()
  const [rescheduling, setRescheduling] = useState(false)
  const [search, setSearch] = useState('')
  const [inputValue, setInputValue] = useState('')
  const [selectedSlot, setSelectedSlot] = useState<OpenSlotsToRescheduleInto200DataItem | null>(
    null,
  )
  const openSlots = useOpenSlotsToRescheduleInto(
    { q: search || undefined, per_page: 100 },
    { query: { enabled: rescheduling } },
  )
  const slotId = selectedSlot?.id

  const invalidate = () => queryClient.invalidateQueries({ queryKey: getMyInterviewQueryKey() })

  if (interviewQuery.isPending) {
    return <CircularProgress aria-label={t('interviews.applicant.loading')} sx={{ mt: 2 }} />
  }

  // A 404 is the expected "not booked yet" state — the applicant is approved for
  // interview but Student Affairs has not scheduled a slot. Any other error is a load
  // failure.
  if (interviewQuery.isError) {
    const notScheduledYet =
      isAxiosError(interviewQuery.error) && interviewQuery.error.response?.status === 404
    return (
      <Alert severity={notScheduledYet ? 'info' : 'error'} sx={{ mt: 2 }}>
        {notScheduledYet
          ? t('interviews.applicant.notScheduled')
          : t('interviews.applicant.loadError')}
      </Alert>
    )
  }

  const interview = interviewQuery.data?.data
  const slot = interview?.slot
  const confirmed = interview?.status === 'confirmed'
  const actionError = confirm.isError
    ? apiErrorMessage(confirm.error)
    : reschedule.isError
      ? apiErrorMessage(reschedule.error)
      : undefined

  const startReschedule = () => {
    setRescheduling(true)
    setSelectedSlot(null)
    setSearch('')
    setInputValue('')
  }

  const submitReschedule = () => {
    if (slotId === undefined) {
      return
    }
    reschedule.mutate(
      { data: { interview_slot_id: Number(slotId) } },
      {
        onSuccess: () => {
          void invalidate()
          setRescheduling(false)
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
    <Box sx={{ mt: 3 }}>
      <Divider textAlign="left" sx={{ mb: 1 }}>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
          <span>{t('interviews.applicant.title')}</span>
          <Chip
            size="small"
            color={confirmed ? 'success' : 'default'}
            label={t(`interviews.status.${interview?.status ?? 'pending_confirmation'}`)}
          />
        </Stack>
      </Divider>

      <Stack spacing={0.5} sx={{ mb: 2 }}>
        {row(t('interviews.applicant.when'), formatSlotWhen(slot?.scheduled_at, i18n.language))}
        {row(t('interviews.applicant.mode'), t(`interviews.modes.${slot?.mode ?? 'in_person'}`))}
        {row(t('interviews.applicant.where'), (slot ? slotWhere(slot) : null) ?? '—')}
        {row(t('interviews.applicant.interviewer'), slot?.interviewer?.name ?? '—')}
      </Stack>

      {actionError && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {actionError}
        </Alert>
      )}

      {rescheduling ? (
        <Stack spacing={2}>
          {openSlots.isError && (
            <Alert severity="error">{t('interviews.applicant.loadError')}</Alert>
          )}
          <Autocomplete
            options={openSlots.data?.data ?? []}
            value={selectedSlot}
            inputValue={inputValue}
            onChange={(_, value) => setSelectedSlot(value)}
            onInputChange={(_, value, reason) => {
              if (reason === 'input' || reason === 'clear') {
                setInputValue(value)
                setSearch(value)
                setSelectedSlot(null)
              } else if (value) {
                setInputValue(value)
              }
            }}
            filterOptions={(options) => options}
            isOptionEqualToValue={(option, value) => option.id === value.id}
            getOptionKey={(option) => option.id ?? 0}
            getOptionLabel={(option) =>
              `${formatSlotWhen(option.scheduled_at, i18n.language)}${slotWhere(option) ? ` (${slotWhere(option)})` : ''}`
            }
            loading={openSlots.isPending}
            loadingText={t('interviews.scheduling.loading')}
            noOptionsText={t('interviews.scheduling.noMatchingSlots')}
            renderInput={(props) => (
              <TextField
                {...props}
                label={t('interviews.applicant.newSlot')}
                fullWidth
                helperText={
                  !openSlots.isPending &&
                  !openSlots.isError &&
                  !search &&
                  openSlots.data?.meta?.total === 0
                    ? t('interviews.applicant.noOpenSlots')
                    : undefined
                }
              />
            )}
          />
          <Stack direction="row" spacing={2}>
            <Button
              variant="contained"
              onClick={submitReschedule}
              disabled={reschedule.isPending || slotId === undefined}
            >
              {t('interviews.applicant.confirmReschedule')}
            </Button>
            <Button onClick={() => setRescheduling(false)} disabled={reschedule.isPending}>
              {t('interviews.cancel')}
            </Button>
          </Stack>
        </Stack>
      ) : (
        <Stack direction="row" spacing={2} sx={{ flexWrap: 'wrap' }}>
          {!confirmed && (
            <Button
              variant="contained"
              color="success"
              onClick={() => confirm.mutate(undefined, { onSuccess: invalidate })}
              disabled={confirm.isPending}
            >
              {t('interviews.applicant.confirm')}
            </Button>
          )}
          {interview?.can_reschedule && (
            <Button variant="outlined" onClick={startReschedule}>
              {t('interviews.applicant.reschedule')}
            </Button>
          )}
        </Stack>
      )}
    </Box>
  )
}
