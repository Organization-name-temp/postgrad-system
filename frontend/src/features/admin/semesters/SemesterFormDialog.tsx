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
  getListSemestersQueryKey,
  useCreateASemester,
  useUpdateASemester,
} from '../../../shared/api/generated/endpoints'
import type { ListSemesters200DataItem } from '../../../shared/api/generated/endpoints'
import { isoToInputValue } from '../../../shared/datetime'

interface SemesterFormDialogProps {
  open: boolean
  onClose: () => void
  /** The Semester being edited, or undefined to create a new one. */
  semester?: ListSemesters200DataItem
}

// Mirrors the backend Term enum (Modules\Calendar\Public\Enums\Term). The server
// re-validates the value against the enum, so this is only for the picker.
const TERMS = ['Fall', 'Spring'] as const

// The seven period dates, listed in the order the server's invariant expects, so
// the admin fills the form top-to-bottom. The server re-validates the ordering and
// stays the authority; a violation surfaces as the inline alert below.
const DATE_FIELDS = [
  'registration_opens_at',
  'registration_closes_at',
  'starts_at',
  'add_drop_ends_at',
  'late_add_ends_at',
  'withdrawal_deadline_at',
  'ends_at',
] as const

type DateField = (typeof DATE_FIELDS)[number]

/**
 * Create or edit a Semester (term + year and the seven period dates). On success it
 * invalidates the semesters list so the table refetches, then closes. A server-side
 * validation failure (duplicate term+year, or dates out of their required order)
 * surfaces as an inline alert — the API remains the authority (ADR-0018).
 */
export function SemesterFormDialog({ open, onClose, semester }: SemesterFormDialogProps) {
  const { t } = useTranslation('admin')
  const queryClient = useQueryClient()
  const isEdit = semester !== undefined

  const [term, setTerm] = useState('')
  const [year, setYear] = useState('')
  const [dates, setDates] = useState<Record<DateField, string>>(() => emptyDates())

  // Seed the fields whenever the dialog opens (for the current semester, or empty
  // for a new one).
  useEffect(() => {
    if (open) {
      setTerm(semester?.term ?? '')
      setYear(semester?.year !== undefined ? String(semester.year) : '')
      setDates(
        Object.fromEntries(
          DATE_FIELDS.map((field) => [field, isoToInputValue(semester?.[field])]),
        ) as Record<DateField, string>,
      )
    }
  }, [open, semester])

  const create = useCreateASemester()
  const update = useUpdateASemester()
  const isSaving = create.isPending || update.isPending
  const isError = create.isError || update.isError

  const onSaved = () => {
    void queryClient.invalidateQueries({ queryKey: getListSemestersQueryKey() })
    onClose()
  }

  const setDate = (field: DateField, value: string) =>
    setDates((current) => ({ ...current, [field]: value }))

  const handleSubmit = () => {
    const data = {
      term,
      year: Number(year),
      starts_at: dates.starts_at,
      ends_at: dates.ends_at,
      registration_opens_at: dates.registration_opens_at,
      registration_closes_at: dates.registration_closes_at,
      add_drop_ends_at: dates.add_drop_ends_at,
      late_add_ends_at: dates.late_add_ends_at,
      withdrawal_deadline_at: dates.withdrawal_deadline_at,
    }
    if (isEdit && semester?.id !== undefined) {
      update.mutate({ semesterId: semester.id, data }, { onSuccess: onSaved })
    } else {
      create.mutate({ data }, { onSuccess: onSaved })
    }
  }

  const isComplete =
    term !== '' && year.trim() !== '' && DATE_FIELDS.every((field) => dates[field] !== '')

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle>{isEdit ? t('semesters.edit') : t('semesters.new')}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ mt: 1 }}>
          {isError && <Alert severity="error">{t('semesters.saveError')}</Alert>}
          <TextField
            select
            label={t('semesters.term')}
            value={term}
            onChange={(event) => setTerm(event.target.value)}
            required
            fullWidth
          >
            {TERMS.map((value) => (
              <MenuItem key={value} value={value}>
                {t(`semesters.terms.${value}`)}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            label={t('semesters.year')}
            type="number"
            value={year}
            onChange={(event) => setYear(event.target.value)}
            required
            fullWidth
          />

          <Typography variant="subtitle2" color="text.secondary">
            {t('semesters.datesHeading')}
          </Typography>
          {DATE_FIELDS.map((field) => (
            <TextField
              key={field}
              label={t(`semesters.fields.${field}`)}
              type="datetime-local"
              value={dates[field]}
              onChange={(event) => setDate(field, event.target.value)}
              required
              fullWidth
              slotProps={{ inputLabel: { shrink: true } }}
            />
          ))}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={isSaving}>
          {t('semesters.cancel')}
        </Button>
        <Button onClick={handleSubmit} variant="contained" disabled={isSaving || !isComplete}>
          {t('semesters.save')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}

function emptyDates(): Record<DateField, string> {
  return Object.fromEntries(DATE_FIELDS.map((field) => [field, ''])) as Record<DateField, string>
}
