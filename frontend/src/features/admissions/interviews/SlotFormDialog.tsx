import { useEffect, useState } from 'react'
import {
  Alert,
  Autocomplete,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  MenuItem,
  Stack,
  TextField,
} from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListInterviewSlotsQueryKey,
  useCreateAnInterviewSlot,
  useListAssignableInterviewers,
  useUpdateAnInterviewSlot,
} from '../../../shared/api/generated/endpoints'
import type {
  ListAssignableInterviewers200DataItem,
  ListInterviewSlots200DataItem,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { isoToInputValue } from '../../../shared/datetime'

interface SlotFormDialogProps {
  open: boolean
  onClose: () => void
  /** The slot being edited, or undefined to create a new one. */
  slot?: ListInterviewSlots200DataItem
}

// Mirrors the backend InterviewMode enum (Admissions\Internal — no generated TS
// counterpart). The server re-validates, so this only drives the picker.
const MODES = ['in_person', 'virtual'] as const

/**
 * Create or edit an interview slot (features/admissions). Student Affairs pick a time,
 * duration, a faculty interviewer (from the assignable list), and a mode with its
 * coordinate — a location for in-person, a meeting link for virtual. On success the
 * slot list is invalidated and the dialog closes; a server rejection (non-faculty
 * interviewer, missing coordinate, a booked slot on edit) surfaces as an inline alert.
 */
export function SlotFormDialog({ open, onClose, slot }: SlotFormDialogProps) {
  const { t } = useTranslation('admissions')
  const queryClient = useQueryClient()
  const isEdit = slot !== undefined

  const [interviewerSearch, setInterviewerSearch] = useState('')
  const [interviewerInput, setInterviewerInput] = useState('')
  const [selectedInterviewer, setSelectedInterviewer] =
    useState<ListAssignableInterviewers200DataItem | null>(null)
  const interviewers = useListAssignableInterviewers(
    { per_page: 100, q: interviewerSearch || undefined },
    { query: { enabled: open } },
  )
  const existingInterviewer = useListAssignableInterviewers(
    { id: String(slot?.interviewer_id ?? ''), per_page: 100 },
    { query: { enabled: open && slot?.interviewer_id !== undefined } },
  )

  const [scheduledAt, setScheduledAt] = useState('')
  const [duration, setDuration] = useState('30')
  const [interviewerId, setInterviewerId] = useState('')
  const [mode, setMode] = useState<string>('in_person')
  const [location, setLocation] = useState('')
  const [meetingLink, setMeetingLink] = useState('')

  useEffect(() => {
    if (open) {
      setScheduledAt(isoToInputValue(slot?.scheduled_at))
      setDuration(slot?.duration_minutes !== undefined ? String(slot.duration_minutes) : '30')
      setInterviewerId(slot?.interviewer_id !== undefined ? String(slot.interviewer_id) : '')
      setInterviewerSearch('')
      setInterviewerInput('')
      setSelectedInterviewer(null)
      setMode(slot?.mode ?? 'in_person')
      setLocation(slot?.location ?? '')
      setMeetingLink(slot?.meeting_link ?? '')
    }
  }, [open, slot])

  useEffect(() => {
    const person = existingInterviewer.data?.data?.find(
      (candidate) => String(candidate.id) === interviewerId,
    )
    if (person && selectedInterviewer === null && interviewerSearch === '') {
      setSelectedInterviewer(person)
      setInterviewerInput(person.name ?? '')
    }
  }, [existingInterviewer.data, interviewerId, selectedInterviewer, interviewerSearch])

  const create = useCreateAnInterviewSlot()
  const update = useUpdateAnInterviewSlot()
  const isSaving = create.isPending || update.isPending
  const error = create.isError
    ? apiErrorMessage(create.error)
    : update.isError
      ? apiErrorMessage(update.error)
      : undefined

  const onSaved = () => {
    void queryClient.invalidateQueries({ queryKey: getListInterviewSlotsQueryKey() })
    onClose()
  }

  const handleSubmit = () => {
    const data = {
      scheduled_at: scheduledAt,
      duration_minutes: Number(duration),
      interviewer_id: Number(interviewerId),
      mode,
      // Only the coordinate relevant to the mode is sent; the other is omitted so the
      // server's required_if rule sees just the one it expects.
      location: mode === 'in_person' ? location : undefined,
      meeting_link: mode === 'virtual' ? meetingLink : undefined,
    }
    if (isEdit && slot?.id !== undefined) {
      update.mutate({ interviewSlotId: slot.id, data }, { onSuccess: onSaved })
    } else {
      create.mutate({ data }, { onSuccess: onSaved })
    }
  }

  const isComplete =
    scheduledAt !== '' &&
    duration.trim() !== '' &&
    interviewerId !== '' &&
    (mode === 'in_person' ? location.trim() !== '' : meetingLink.trim() !== '')

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle>{isEdit ? t('interviews.slots.edit') : t('interviews.slots.new')}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ mt: 1 }}>
          {error && <Alert severity="error">{error}</Alert>}
          <TextField
            label={t('interviews.slots.scheduledAt')}
            type="datetime-local"
            value={scheduledAt}
            onChange={(event) => setScheduledAt(event.target.value)}
            required
            fullWidth
            slotProps={{ inputLabel: { shrink: true } }}
          />
          <TextField
            label={t('interviews.slots.duration')}
            type="number"
            value={duration}
            onChange={(event) => setDuration(event.target.value)}
            required
            fullWidth
          />
          <Autocomplete
            options={interviewers.data?.data ?? []}
            value={selectedInterviewer}
            inputValue={interviewerInput}
            filterOptions={(options) => options}
            getOptionLabel={(person) => person.name ?? ''}
            getOptionKey={(person) => person.id ?? ''}
            isOptionEqualToValue={(option, value) => option.id === value.id}
            loading={interviewers.isFetching}
            loadingText={t('interviews.slots.interviewersLoading')}
            noOptionsText={t('interviews.slots.noMatchingInterviewers')}
            onInputChange={(_event, value, reason) => {
              setInterviewerInput(value)
              if (reason === 'input' || reason === 'clear') {
                setInterviewerSearch(value)
                setSelectedInterviewer(null)
                setInterviewerId('')
              }
            }}
            onChange={(_event, person) => {
              setSelectedInterviewer(person)
              setInterviewerId(person?.id !== undefined ? String(person.id) : '')
            }}
            renderInput={(params) => (
              <TextField
                {...params}
                label={t('interviews.slots.interviewer')}
                required
                fullWidth
                helperText={
                  interviewers.isError ? t('interviews.slots.interviewersError') : undefined
                }
                error={interviewers.isError}
              />
            )}
          />
          <TextField
            select
            label={t('interviews.slots.mode')}
            value={mode}
            onChange={(event) => setMode(event.target.value)}
            required
            fullWidth
          >
            {MODES.map((value) => (
              <MenuItem key={value} value={value}>
                {t(`interviews.modes.${value}`)}
              </MenuItem>
            ))}
          </TextField>
          {mode === 'in_person' ? (
            <TextField
              label={t('interviews.slots.location')}
              value={location}
              onChange={(event) => setLocation(event.target.value)}
              required
              fullWidth
            />
          ) : (
            <TextField
              label={t('interviews.slots.meetingLink')}
              value={meetingLink}
              onChange={(event) => setMeetingLink(event.target.value)}
              required
              fullWidth
            />
          )}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={isSaving}>
          {t('interviews.cancel')}
        </Button>
        <Button onClick={handleSubmit} variant="contained" disabled={isSaving || !isComplete}>
          {t('interviews.save')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
