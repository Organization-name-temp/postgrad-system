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
} from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListAdmissionCyclesQueryKey,
  useCreateAnAdmissionCycle,
  useUpdateAnAdmissionCycle,
} from '../../../shared/api/generated/endpoints'
import type { ListAdmissionCycles200DataItem } from '../../../shared/api/generated/endpoints'
import { isoToInputValue } from './dateTime'

interface CycleFormDialogProps {
  open: boolean
  onClose: () => void
  /** The Cycle being edited, or undefined to create a new one. */
  cycle?: ListAdmissionCycles200DataItem
}

// Mirrors the backend IntakeTerm enum (Modules\Admissions\Internal\Enums —
// Internal, so it has no generated TS counterpart to import). The server
// re-validates the value against the enum, so this is only for the picker.
const INTAKE_TERMS = ['Fall', 'Spring'] as const

/**
 * Create or edit an Admission Cycle (intake term + year, English/Arabic names,
 * and the open/close window). On success it invalidates the cycles list so the
 * table refetches, then closes. A server-side validation failure (duplicate
 * term+year, missing name, close-before-open) surfaces as an inline alert — the
 * API remains the authority.
 */
export function CycleFormDialog({ open, onClose, cycle }: CycleFormDialogProps) {
  const { t } = useTranslation('admin')
  const queryClient = useQueryClient()
  const isEdit = cycle !== undefined

  const [intakeTerm, setIntakeTerm] = useState('')
  const [year, setYear] = useState('')
  const [nameEn, setNameEn] = useState('')
  const [nameAr, setNameAr] = useState('')
  const [opensAt, setOpensAt] = useState('')
  const [closesAt, setClosesAt] = useState('')

  // Seed the fields whenever the dialog opens (for the current cycle, or empty
  // for a new one).
  useEffect(() => {
    if (open) {
      setIntakeTerm(cycle?.intake_term ?? '')
      setYear(cycle?.year !== undefined ? String(cycle.year) : '')
      setNameEn(cycle?.name_en ?? '')
      setNameAr(cycle?.name_ar ?? '')
      setOpensAt(isoToInputValue(cycle?.opens_at))
      setClosesAt(isoToInputValue(cycle?.closes_at))
    }
  }, [open, cycle])

  const create = useCreateAnAdmissionCycle()
  const update = useUpdateAnAdmissionCycle()
  const isSaving = create.isPending || update.isPending
  const isError = create.isError || update.isError

  const onSaved = () => {
    void queryClient.invalidateQueries({ queryKey: getListAdmissionCyclesQueryKey() })
    onClose()
  }

  const handleSubmit = () => {
    const data = {
      intake_term: intakeTerm,
      year: Number(year),
      name_en: nameEn,
      name_ar: nameAr,
      opens_at: opensAt,
      closes_at: closesAt,
    }
    if (isEdit && cycle?.id !== undefined) {
      update.mutate({ admissionCycleId: cycle.id, data }, { onSuccess: onSaved })
    } else {
      create.mutate({ data }, { onSuccess: onSaved })
    }
  }

  const isComplete =
    intakeTerm !== '' &&
    year.trim() !== '' &&
    nameEn.trim() !== '' &&
    nameAr.trim() !== '' &&
    opensAt !== '' &&
    closesAt !== ''

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle>{isEdit ? t('cycles.edit') : t('cycles.new')}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ mt: 1 }}>
          {isError && <Alert severity="error">{t('cycles.saveError')}</Alert>}
          <TextField
            select
            label={t('cycles.intakeTerm')}
            value={intakeTerm}
            onChange={(event) => setIntakeTerm(event.target.value)}
            required
            fullWidth
          >
            {INTAKE_TERMS.map((term) => (
              <MenuItem key={term} value={term}>
                {t(`cycles.terms.${term}`)}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            label={t('cycles.year')}
            type="number"
            value={year}
            onChange={(event) => setYear(event.target.value)}
            required
            fullWidth
          />
          <TextField
            label={t('cycles.nameEn')}
            value={nameEn}
            onChange={(event) => setNameEn(event.target.value)}
            required
            fullWidth
          />
          <TextField
            label={t('cycles.nameAr')}
            value={nameAr}
            onChange={(event) => setNameAr(event.target.value)}
            required
            fullWidth
          />
          <TextField
            label={t('cycles.opensAt')}
            type="datetime-local"
            value={opensAt}
            onChange={(event) => setOpensAt(event.target.value)}
            required
            fullWidth
            slotProps={{ inputLabel: { shrink: true } }}
          />
          <TextField
            label={t('cycles.closesAt')}
            type="datetime-local"
            value={closesAt}
            onChange={(event) => setClosesAt(event.target.value)}
            required
            fullWidth
            slotProps={{ inputLabel: { shrink: true } }}
          />
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={isSaving}>
          {t('cycles.cancel')}
        </Button>
        <Button onClick={handleSubmit} variant="contained" disabled={isSaving || !isComplete}>
          {t('cycles.save')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
