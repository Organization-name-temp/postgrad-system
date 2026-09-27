import { useEffect, useState } from 'react'
import {
  Alert,
  Autocomplete,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getApplicationsAwaitingInterviewSchedulingQueryKey,
  getBookedInterviewsQueryKey,
  getListInterviewSlotsQueryKey,
  useListInterviewSlots,
  useScheduleAnApplicantIntoAnInterviewSlot,
} from '../../../shared/api/generated/endpoints'
import type { ListInterviewSlots200DataItem } from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { formatSlotWhen, slotWhere } from './slotDisplay'

interface ScheduleDialogProps {
  open: boolean
  onClose: () => void
  applicationId: number | undefined
  applicantName: string
}

/**
 * Book an approved applicant into an open interview slot (features/admissions). The
 * picker lists only the slots still open; scheduling notifies the applicant and moves
 * them off the worklist. A server rejection (slot just taken, application no longer
 * awaiting) surfaces inline.
 */
export function ScheduleDialog({
  open,
  onClose,
  applicationId,
  applicantName,
}: ScheduleDialogProps) {
  const { t, i18n } = useTranslation('admissions')
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [inputValue, setInputValue] = useState('')
  const [selectedSlot, setSelectedSlot] = useState<ListInterviewSlots200DataItem | null>(null)
  const slots = useListInterviewSlots(
    { is_open: 'true', per_page: 100, q: search || undefined },
    { query: { enabled: open } },
  )
  const openSlots = (slots.data?.data ?? []).filter((slot) => slot.is_open)

  const slotId = selectedSlot?.id
  useEffect(() => {
    if (open) {
      setSelectedSlot(null)
      setSearch('')
      setInputValue('')
    }
  }, [open])

  const schedule = useScheduleAnApplicantIntoAnInterviewSlot()
  const error = schedule.isError ? apiErrorMessage(schedule.error) : undefined

  const handleSubmit = () => {
    if (applicationId === undefined || slotId === undefined) {
      return
    }
    schedule.mutate(
      { applicationId, data: { interview_slot_id: Number(slotId) } },
      {
        onSuccess: () => {
          void queryClient.invalidateQueries({
            queryKey: getApplicationsAwaitingInterviewSchedulingQueryKey(),
          })
          void queryClient.invalidateQueries({ queryKey: getBookedInterviewsQueryKey() })
          void queryClient.invalidateQueries({ queryKey: getListInterviewSlotsQueryKey() })
          onClose()
        },
      },
    )
  }

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle>{t('interviews.scheduling.dialogTitle', { name: applicantName })}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ mt: 1 }}>
          {error && <Alert severity="error">{error}</Alert>}
          {slots.isError && <Alert severity="error">{t('interviews.scheduling.loadError')}</Alert>}
          {!slots.isPending &&
            !slots.isError &&
            !search &&
            (slots.data?.meta?.total ?? 0) === 0 && (
              <Typography color="text.secondary">
                {t('interviews.scheduling.noOpenSlots')}
              </Typography>
            )}
          <Autocomplete
            options={openSlots}
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
            getOptionLabel={(slot) =>
              `${formatSlotWhen(slot.scheduled_at, i18n.language)} — ${slot.interviewer?.name ?? ''}${slotWhere(slot) ? ` (${slotWhere(slot)})` : ''}`
            }
            loading={slots.isPending}
            loadingText={t('interviews.scheduling.loading')}
            noOptionsText={t('interviews.scheduling.noMatchingSlots')}
            renderInput={(props) => (
              <TextField {...props} label={t('interviews.scheduling.slot')} required fullWidth />
            )}
          />
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={schedule.isPending}>
          {t('interviews.cancel')}
        </Button>
        <Button
          onClick={handleSubmit}
          variant="contained"
          disabled={schedule.isPending || slotId === undefined}
        >
          {t('interviews.scheduling.confirm')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
