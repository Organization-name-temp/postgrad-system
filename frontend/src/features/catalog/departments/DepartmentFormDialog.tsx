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
  getListDepartmentsQueryKey,
  useCreateADepartment,
  useListFaculties,
  useUpdateADepartment,
} from '../../../shared/api/generated/endpoints'
import type { ListDepartments200DataItem } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../localeName'

interface DepartmentFormDialogProps {
  open: boolean
  onClose: () => void
  /** The Department being edited, or undefined to create a new one. */
  department?: ListDepartments200DataItem
}

/**
 * Create or edit a Department (owning Faculty + code + English/Arabic names). The
 * Faculty is chosen from the existing Faculties. On success it invalidates the
 * departments list and closes; a server validation failure surfaces inline.
 */
export function DepartmentFormDialog({ open, onClose, department }: DepartmentFormDialogProps) {
  const { t, i18n } = useTranslation('catalog')
  const queryClient = useQueryClient()
  const isEdit = department !== undefined

  const { data: facultiesData } = useListFaculties()
  const faculties = facultiesData?.data ?? []

  const [facultyId, setFacultyId] = useState('')
  const [code, setCode] = useState('')
  const [nameEn, setNameEn] = useState('')
  const [nameAr, setNameAr] = useState('')

  useEffect(() => {
    if (open) {
      setFacultyId(department?.faculty_id != null ? String(department.faculty_id) : '')
      setCode(department?.code ?? '')
      setNameEn(department?.name_en ?? '')
      setNameAr(department?.name_ar ?? '')
    }
  }, [open, department])

  const create = useCreateADepartment()
  const update = useUpdateADepartment()
  const isSaving = create.isPending || update.isPending
  const isError = create.isError || update.isError

  const onSaved = () => {
    void queryClient.invalidateQueries({ queryKey: getListDepartmentsQueryKey() })
    onClose()
  }

  const handleSubmit = () => {
    const data = { faculty_id: Number(facultyId), code, name_en: nameEn, name_ar: nameAr }
    if (isEdit && department?.id !== undefined) {
      update.mutate({ departmentId: department.id, data }, { onSuccess: onSaved })
    } else {
      create.mutate({ data }, { onSuccess: onSaved })
    }
  }

  const isComplete =
    facultyId !== '' && code.trim() !== '' && nameEn.trim() !== '' && nameAr.trim() !== ''

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle>{isEdit ? t('departments.edit') : t('departments.new')}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ mt: 1 }}>
          {isError && <Alert severity="error">{t('departments.saveError')}</Alert>}
          <TextField
            select
            label={t('departments.faculty')}
            value={facultyId}
            onChange={(event) => setFacultyId(event.target.value)}
            required
            fullWidth
          >
            {faculties.map((faculty) => (
              <MenuItem key={faculty.id} value={String(faculty.id)}>
                {pickLocaleName(faculty, i18n.language, faculty.code ?? '')}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            label={t('departments.code')}
            value={code}
            onChange={(event) => setCode(event.target.value)}
            required
            fullWidth
          />
          <TextField
            label={t('departments.nameEn')}
            value={nameEn}
            onChange={(event) => setNameEn(event.target.value)}
            required
            fullWidth
          />
          <TextField
            label={t('departments.nameAr')}
            value={nameAr}
            onChange={(event) => setNameAr(event.target.value)}
            required
            fullWidth
          />
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={isSaving}>
          {t('departments.cancel')}
        </Button>
        <Button onClick={handleSubmit} variant="contained" disabled={isSaving || !isComplete}>
          {t('departments.save')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
