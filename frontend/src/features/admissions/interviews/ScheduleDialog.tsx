import { useEffect, useState } from 'react'
import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  MenuItem,
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
  const slots = useListInterviewSlots(
    { is_open: 'true', per_page: 100 },
    { query: { enabled: open } },
  )
  const openSlots = (slots.data?.data ?? []).filter((slot) => slot.is_open)

  const [slotId, setSlotId] = useState('')
  useEffect(() => {
    if (open) {
      setSlotId('')
    }
  }, [open])

  const schedule = useScheduleAnApplicantIntoAnInterviewSlot()
  const error = schedule.isError ? apiErrorMessage(schedule.error) : undefined

  const handleSubmit = () => {
    if (applicationId === undefined || slotId === '') {
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
          {openSlots.length === 0 ? (
            <Typography color="text.secondary">{t('interviews.scheduling.noOpenSlots')}</Typography>
          ) : (
            <TextField
              select
              label={t('interviews.scheduling.slot')}
              value={slotId}
              onChange={(event) => setSlotId(event.target.value)}
              required
              fullWidth
            >
              {openSlots.map((slot) => (
                <MenuItem key={slot.id} value={String(slot.id)}>
                  {formatSlotWhen(slot.scheduled_at, i18n.language)} — {slot.interviewer?.name}
                  {slotWhere(slot) ? ` (${slotWhere(slot)})` : ''}
                </MenuItem>
              ))}
            </TextField>
          )}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={schedule.isPending}>
          {t('interviews.cancel')}
        </Button>
        <Button
          onClick={handleSubmit}
          variant="contained"
          disabled={schedule.isPending || slotId === ''}
        >
          {t('interviews.scheduling.confirm')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
