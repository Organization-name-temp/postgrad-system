import { useEffect, useState } from 'react'
import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Stack,
  TextField,
} from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListFacultiesQueryKey,
  useCreateAFaculty,
  useUpdateAFaculty,
} from '../../../shared/api/generated/endpoints'
import type { ListFaculties200DataItem } from '../../../shared/api/generated/endpoints'

interface FacultyFormDialogProps {
  open: boolean
  onClose: () => void
  /** The Faculty being edited, or undefined to create a new one. */
  faculty?: ListFaculties200DataItem
}

/**
 * Create or edit a Faculty (code + English/Arabic names). On success it
 * invalidates the faculties list so the table refetches, then closes. A
 * server-side validation failure (duplicate code, missing name) surfaces as an
 * inline alert — the API remains the authority.
 */
export function FacultyFormDialog({ open, onClose, faculty }: FacultyFormDialogProps) {
  const { t } = useTranslation('catalog')
  const queryClient = useQueryClient()
  const isEdit = faculty !== undefined

  const [code, setCode] = useState('')
  const [nameEn, setNameEn] = useState('')
  const [nameAr, setNameAr] = useState('')

  // Seed the fields whenever the dialog opens (for the current faculty, or empty
  // for a new one).
  useEffect(() => {
    if (open) {
      setCode(faculty?.code ?? '')
      setNameEn(faculty?.name_en ?? '')
      setNameAr(faculty?.name_ar ?? '')
    }
  }, [open, faculty])

  const create = useCreateAFaculty()
  const update = useUpdateAFaculty()
  const isSaving = create.isPending || update.isPending
  const isError = create.isError || update.isError

  const onSaved = () => {
    void queryClient.invalidateQueries({ queryKey: getListFacultiesQueryKey() })
    onClose()
  }

  const handleSubmit = () => {
    const data = { code, name_en: nameEn, name_ar: nameAr }
    if (isEdit && faculty?.id !== undefined) {
      update.mutate({ facultyId: faculty.id, data }, { onSuccess: onSaved })
    } else {
      create.mutate({ data }, { onSuccess: onSaved })
    }
  }

  const isComplete = code.trim() !== '' && nameEn.trim() !== '' && nameAr.trim() !== ''

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle>{isEdit ? t('faculties.edit') : t('faculties.new')}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ mt: 1 }}>
          {isError && <Alert severity="error">{t('faculties.saveError')}</Alert>}
          <TextField
            label={t('faculties.code')}
            value={code}
            onChange={(event) => setCode(event.target.value)}
            required
            fullWidth
          />
          <TextField
            label={t('faculties.nameEn')}
            value={nameEn}
            onChange={(event) => setNameEn(event.target.value)}
            required
            fullWidth
          />
          <TextField
            label={t('faculties.nameAr')}
            value={nameAr}
            onChange={(event) => setNameAr(event.target.value)}
            required
            fullWidth
          />
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={isSaving}>
          {t('faculties.cancel')}
        </Button>
        <Button onClick={handleSubmit} variant="contained" disabled={isSaving || !isComplete}>
          {t('faculties.save')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
