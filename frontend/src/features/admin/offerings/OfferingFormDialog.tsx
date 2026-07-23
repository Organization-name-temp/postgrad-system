import { useEffect, useState } from 'react'
import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  IconButton,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlined'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListASemestersCourseOfferingsQueryKey,
  useCreateACourseOfferingInASemester,
  useListAssignableInstructors,
  useListCourses,
  useUpdateACourseOffering,
} from '../../../shared/api/generated/endpoints'
import type { ListASemestersCourseOfferings200DataItem } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { hasSelfOverlap, isMeetingComplete, WEEKDAY_VALUES, type MeetingDraft } from './meetingTime'

interface OfferingFormDialogProps {
  open: boolean
  onClose: () => void
  semesterId: number
  /** The Offering being edited, or undefined to create a new one. */
  offering?: ListASemestersCourseOfferings200DataItem
}

const blankMeeting = (): MeetingDraft => ({ weekday: '', starts_at: '', ends_at: '' })

/**
 * Create or edit a Course Offering: a Catalog course (fixed once created), an
 * instructor, an optional capacity, and one or more weekly meeting slots. The course
 * and instructor pickers read the Catalog courses list and the R04 instructor list; the
 * meeting editor uses plain time inputs (no new deps). Client-side checks mirror the
 * server (ADR-0014) — complete rows, end after start, no self-overlap — but the API
 * re-validates and stays the authority; a 422 surfaces as the inline alert.
 */
export function OfferingFormDialog({
  open,
  onClose,
  semesterId,
  offering,
}: OfferingFormDialogProps) {
  const { t, i18n } = useTranslation('admin')
  const queryClient = useQueryClient()
  const isEdit = offering !== undefined

  const courses = useListCourses().data?.data ?? []
  const instructors = useListAssignableInstructors().data?.data ?? []

  const [courseId, setCourseId] = useState('')
  const [instructorId, setInstructorId] = useState('')
  const [capacity, setCapacity] = useState('')
  const [meetings, setMeetings] = useState<MeetingDraft[]>([blankMeeting()])

  // Seed the fields whenever the dialog opens (from the edited offering, or blank).
  useEffect(() => {
    if (!open) {
      return
    }
    setCourseId(offering?.course_id !== undefined ? String(offering.course_id) : '')
    setInstructorId(offering?.instructor_id !== undefined ? String(offering.instructor_id) : '')
    setCapacity(
      offering?.capacity !== undefined && offering.capacity !== null
        ? String(offering.capacity)
        : '',
    )
    setMeetings(
      offering?.meetings && offering.meetings.length > 0
        ? offering.meetings.map((meeting) => ({
            weekday: meeting.weekday !== undefined ? String(meeting.weekday) : '',
            starts_at: meeting.starts_at ?? '',
            ends_at: meeting.ends_at ?? '',
          }))
        : [blankMeeting()],
    )
  }, [open, offering])

  const create = useCreateACourseOfferingInASemester()
  const update = useUpdateACourseOffering()
  const isSaving = create.isPending || update.isPending
  const isError = create.isError || update.isError

  const setMeeting = (index: number, patch: Partial<MeetingDraft>) =>
    setMeetings((current) =>
      current.map((meeting, i) => (i === index ? { ...meeting, ...patch } : meeting)),
    )
  const addMeeting = () => setMeetings((current) => [...current, blankMeeting()])
  const removeMeeting = (index: number) =>
    setMeetings((current) => (current.length > 1 ? current.filter((_, i) => i !== index) : current))

  const meetingsComplete = meetings.length > 0 && meetings.every(isMeetingComplete)
  const overlaps = hasSelfOverlap(meetings)
  const isComplete =
    (isEdit || courseId !== '') && instructorId !== '' && meetingsComplete && !overlaps

  const onSaved = () => {
    void queryClient.invalidateQueries({
      queryKey: getListASemestersCourseOfferingsQueryKey(semesterId),
    })
    onClose()
  }

  const handleSubmit = () => {
    const meetingsPayload = meetings.map((meeting) => ({
      weekday: Number(meeting.weekday),
      starts_at: meeting.starts_at,
      ends_at: meeting.ends_at,
    }))
    const capacityValue = capacity.trim() === '' ? undefined : Number(capacity)

    if (isEdit && offering?.id !== undefined) {
      update.mutate(
        {
          offeringId: offering.id,
          data: {
            instructor_id: Number(instructorId),
            capacity: capacityValue,
            meetings: meetingsPayload,
          },
        },
        { onSuccess: onSaved },
      )
    } else {
      create.mutate(
        {
          semesterId,
          data: {
            course_id: Number(courseId),
            instructor_id: Number(instructorId),
            capacity: capacityValue,
            meetings: meetingsPayload,
          },
        },
        { onSuccess: onSaved },
      )
    }
  }

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle>{isEdit ? t('offerings.edit') : t('offerings.new')}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ mt: 1 }}>
          {isError && <Alert severity="error">{t('offerings.saveError')}</Alert>}

          <TextField
            select
            label={t('offerings.course')}
            value={courseId}
            onChange={(event) => setCourseId(event.target.value)}
            required
            fullWidth
            disabled={isEdit}
            helperText={isEdit ? t('offerings.courseFixed') : undefined}
          >
            {courses
              .filter((course) => course.is_active)
              .map((course) => (
                <MenuItem key={course.id} value={String(course.id)}>
                  {course.code} — {pickLocaleName(course, i18n.language)}
                </MenuItem>
              ))}
          </TextField>

          <TextField
            select
            label={t('offerings.instructor')}
            value={instructorId}
            onChange={(event) => setInstructorId(event.target.value)}
            required
            fullWidth
          >
            {instructors.map((person) => (
              <MenuItem key={person.id} value={String(person.id)}>
                {person.name}
              </MenuItem>
            ))}
          </TextField>

          <TextField
            label={t('offerings.capacity')}
            type="number"
            value={capacity}
            onChange={(event) => setCapacity(event.target.value)}
            fullWidth
            helperText={t('offerings.capacityHint')}
            slotProps={{ htmlInput: { min: 1 } }}
          />

          <Divider />
          <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
            <Typography variant="subtitle2" color="text.secondary">
              {t('offerings.meetings')}
            </Typography>
            <Button size="small" startIcon={<AddIcon />} onClick={addMeeting}>
              {t('offerings.addMeeting')}
            </Button>
          </Stack>

          {overlaps && <Alert severity="warning">{t('offerings.overlapWarning')}</Alert>}

          {meetings.map((meeting, index) => (
            <Stack key={index} direction="row" spacing={1} sx={{ alignItems: 'flex-start' }}>
              <TextField
                select
                label={t('offerings.weekday')}
                value={meeting.weekday}
                onChange={(event) => setMeeting(index, { weekday: event.target.value })}
                required
                sx={{ minWidth: 120 }}
              >
                {WEEKDAY_VALUES.map((day) => (
                  <MenuItem key={day} value={String(day)}>
                    {t(`offerings.weekdays.${day}`)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label={t('offerings.startsAt')}
                type="time"
                value={meeting.starts_at}
                onChange={(event) => setMeeting(index, { starts_at: event.target.value })}
                required
                slotProps={{ inputLabel: { shrink: true } }}
              />
              <TextField
                label={t('offerings.endsAt')}
                type="time"
                value={meeting.ends_at}
                onChange={(event) => setMeeting(index, { ends_at: event.target.value })}
                required
                slotProps={{ inputLabel: { shrink: true } }}
              />
              <IconButton
                aria-label={t('offerings.removeMeeting')}
                onClick={() => removeMeeting(index)}
                disabled={meetings.length === 1}
                sx={{ mt: 1 }}
              >
                <DeleteOutlineIcon fontSize="small" />
              </IconButton>
            </Stack>
          ))}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={isSaving}>
          {t('offerings.cancel')}
        </Button>
        <Button onClick={handleSubmit} variant="contained" disabled={isSaving || !isComplete}>
          {t('offerings.save')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
