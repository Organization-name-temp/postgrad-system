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
  getListProgramsQueryKey,
  useCreateAProgram,
  useListDepartments,
  useUpdateAProgram,
} from '../../../shared/api/generated/endpoints'
import type { ListPrograms200DataItem } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../localeName'
import { DEGREE_LEVELS } from './degreeLevels'

interface ProgramFormDialogProps {
  open: boolean
  onClose: () => void
  /** The Program being edited, or undefined to create a new one. */
  program?: ListPrograms200DataItem
}

// The bylaw default credit-hour split (6/6/9), applied to a new Program's form so
// the common case needs no typing; the server applies the same defaults (BR-14).
const DEFAULT_CH = { prep: '6', core: '6', elective: '9' } as const

/**
 * Create or edit a Program (owning Department + code + English/Arabic names +
 * degree level + per-Category required credit hours). The Department is chosen
 * from the existing Departments and the degree level from the fixed MSc/PhD set.
 * On success it invalidates the programs list and closes; a server validation
 * failure surfaces inline.
 */
export function ProgramFormDialog({ open, onClose, program }: ProgramFormDialogProps) {
  const { t, i18n } = useTranslation('catalog')
  const queryClient = useQueryClient()
  const isEdit = program !== undefined

  const { data: departmentsData } = useListDepartments()
  const departments = departmentsData?.data ?? []

  const [departmentId, setDepartmentId] = useState('')
  const [code, setCode] = useState('')
  const [nameEn, setNameEn] = useState('')
  const [nameAr, setNameAr] = useState('')
  const [degreeLevel, setDegreeLevel] = useState('')
  const [prepCh, setPrepCh] = useState<string>(DEFAULT_CH.prep)
  const [coreCh, setCoreCh] = useState<string>(DEFAULT_CH.core)
  const [electiveCh, setElectiveCh] = useState<string>(DEFAULT_CH.elective)

  useEffect(() => {
    if (open) {
      setDepartmentId(program?.department_id != null ? String(program.department_id) : '')
      setCode(program?.code ?? '')
      setNameEn(program?.name_en ?? '')
      setNameAr(program?.name_ar ?? '')
      setDegreeLevel(program?.degree_level ?? '')
      setPrepCh(
        program?.prep_required_ch != null ? String(program.prep_required_ch) : DEFAULT_CH.prep,
      )
      setCoreCh(
        program?.core_required_ch != null ? String(program.core_required_ch) : DEFAULT_CH.core,
      )
      setElectiveCh(
        program?.elective_required_ch != null
          ? String(program.elective_required_ch)
          : DEFAULT_CH.elective,
      )
    }
  }, [open, program])

  const create = useCreateAProgram()
  const update = useUpdateAProgram()
  const isSaving = create.isPending || update.isPending
  const isError = create.isError || update.isError

  const onSaved = () => {
    void queryClient.invalidateQueries({ queryKey: getListProgramsQueryKey() })
    onClose()
  }

  const handleSubmit = () => {
    const data = {
      department_id: Number(departmentId),
      code,
      name_en: nameEn,
      name_ar: nameAr,
      degree_level: degreeLevel,
      prep_required_ch: Number(prepCh),
      core_required_ch: Number(coreCh),
      elective_required_ch: Number(electiveCh),
    }
    if (isEdit && program?.id !== undefined) {
      update.mutate({ programId: program.id, data }, { onSuccess: onSaved })
    } else {
      create.mutate({ data }, { onSuccess: onSaved })
    }
  }

  const chFilled = (value: string) => value.trim() !== '' && Number(value) >= 0
  const isComplete =
    departmentId !== '' &&
    code.trim() !== '' &&
    nameEn.trim() !== '' &&
    nameAr.trim() !== '' &&
    degreeLevel !== '' &&
    chFilled(prepCh) &&
    chFilled(coreCh) &&
    chFilled(electiveCh)

  const total = [prepCh, coreCh, electiveCh].reduce((sum, value) => sum + (Number(value) || 0), 0)

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle>{isEdit ? t('programs.edit') : t('programs.new')}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ mt: 1 }}>
          {isError && <Alert severity="error">{t('programs.saveError')}</Alert>}
          <TextField
            select
            label={t('programs.department')}
            value={departmentId}
            onChange={(event) => setDepartmentId(event.target.value)}
            required
            fullWidth
          >
            {departments.map((department) => (
              <MenuItem key={department.id} value={String(department.id)}>
                {pickLocaleName(department, i18n.language, department.code ?? '')}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            label={t('programs.code')}
            value={code}
            onChange={(event) => setCode(event.target.value)}
            required
            fullWidth
          />
          <TextField
            label={t('programs.nameEn')}
            value={nameEn}
            onChange={(event) => setNameEn(event.target.value)}
            required
            fullWidth
          />
          <TextField
            label={t('programs.nameAr')}
            value={nameAr}
            onChange={(event) => setNameAr(event.target.value)}
            required
            fullWidth
          />
          <TextField
            select
            label={t('programs.degreeLevel')}
            value={degreeLevel}
            onChange={(event) => setDegreeLevel(event.target.value)}
            required
            fullWidth
          >
            {DEGREE_LEVELS.map((level) => (
              <MenuItem key={level} value={level}>
                {t(`programs.degreeLevels.${level}`)}
              </MenuItem>
            ))}
          </TextField>
          <Typography variant="subtitle2" color="text.secondary">
            {t('programs.creditRequirements')}
          </Typography>
          <Stack direction="row" spacing={2}>
            <TextField
              type="number"
              label={t('programs.prepCh')}
              value={prepCh}
              onChange={(event) => setPrepCh(event.target.value)}
              slotProps={{ htmlInput: { min: 0 } }}
              required
              fullWidth
            />
            <TextField
              type="number"
              label={t('programs.coreCh')}
              value={coreCh}
              onChange={(event) => setCoreCh(event.target.value)}
              slotProps={{ htmlInput: { min: 0 } }}
              required
              fullWidth
            />
            <TextField
              type="number"
              label={t('programs.electiveCh')}
              value={electiveCh}
              onChange={(event) => setElectiveCh(event.target.value)}
              slotProps={{ htmlInput: { min: 0 } }}
              required
              fullWidth
            />
          </Stack>
          <Typography variant="body2" color="text.secondary">
            {t('programs.totalCh', { total })}
          </Typography>
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={isSaving}>
          {t('programs.cancel')}
        </Button>
        <Button onClick={handleSubmit} variant="contained" disabled={isSaving || !isComplete}>
          {t('programs.save')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
