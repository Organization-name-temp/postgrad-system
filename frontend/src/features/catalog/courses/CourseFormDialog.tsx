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
  getListACoursesVersionHistoryQueryKey,
  getListCoursesQueryKey,
  getShowACourseQueryKey,
  useCreateACourse,
  useListDepartments,
  useUpdateACourse,
} from '../../../shared/api/generated/endpoints'
import type {
  CreateACourseBody,
  ListCourses200DataItem,
} from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../localeName'
import { COURSE_LEVELS } from './courseLevels'

interface CourseFormDialogProps {
  open: boolean
  onClose: () => void
  /** The Course being edited, or undefined to create a new one. */
  course?: ListCourses200DataItem
}

// Sentinel value for the "university-wide" (no Department) picker option; the empty
// string is never a valid Department id, so it cleanly distinguishes "no owner"
// from a chosen Department.
const UNIVERSITY_WIDE = ''

/**
 * Create or edit a Course (code + English/Arabic names + level + the
 * Lecture/Tutorial/Lab/Total credit-hour breakdown + an owning Department or
 * "university-wide"). The Department is chosen from the existing Departments plus a
 * university-wide option (the shared Preparatory courses); the level from the fixed
 * 400/500/600/700 set. On success it invalidates the courses list and closes; a
 * server validation failure — including an unbalanced credit split — surfaces
 * inline.
 */
export function CourseFormDialog({ open, onClose, course }: CourseFormDialogProps) {
  const { t, i18n } = useTranslation('catalog')
  const queryClient = useQueryClient()
  const isEdit = course !== undefined

  const { data: departmentsData } = useListDepartments()
  const departments = departmentsData?.data ?? []

  const [departmentId, setDepartmentId] = useState<string>(UNIVERSITY_WIDE)
  const [code, setCode] = useState('')
  const [nameEn, setNameEn] = useState('')
  const [nameAr, setNameAr] = useState('')
  const [level, setLevel] = useState('')
  const [lectureCh, setLectureCh] = useState('')
  const [tutorialCh, setTutorialCh] = useState('')
  const [labCh, setLabCh] = useState('')
  const [totalCh, setTotalCh] = useState('')

  useEffect(() => {
    if (open) {
      setDepartmentId(
        course?.department_id != null ? String(course.department_id) : UNIVERSITY_WIDE,
      )
      setCode(course?.code ?? '')
      setNameEn(course?.name_en ?? '')
      setNameAr(course?.name_ar ?? '')
      setLevel(course?.level != null ? String(course.level) : '')
      setLectureCh(course?.lecture_ch != null ? String(course.lecture_ch) : '')
      setTutorialCh(course?.tutorial_ch != null ? String(course.tutorial_ch) : '')
      setLabCh(course?.lab_ch != null ? String(course.lab_ch) : '')
      setTotalCh(course?.total_ch != null ? String(course.total_ch) : '')
    }
  }, [open, course])

  const create = useCreateACourse()
  const update = useUpdateACourse()
  const isSaving = create.isPending || update.isPending
  const isError = create.isError || update.isError

  const onSaved = () => {
    void queryClient.invalidateQueries({ queryKey: getListCoursesQueryKey() })
    // Also refresh the single-Course view and its version history (the edit page
    // reads both) after an edit, so the change and the new version show without a
    // reload.
    if (isEdit && course?.id !== undefined) {
      void queryClient.invalidateQueries({ queryKey: getShowACourseQueryKey(course.id) })
      void queryClient.invalidateQueries({
        queryKey: getListACoursesVersionHistoryQueryKey(course.id),
      })
    }
    onClose()
  }

  const handleSubmit = () => {
    const data: CreateACourseBody = {
      code,
      name_en: nameEn,
      name_ar: nameAr,
      level: Number(level),
      lecture_ch: Number(lectureCh),
      tutorial_ch: Number(tutorialCh),
      lab_ch: Number(labCh),
      total_ch: Number(totalCh),
    }
    // Only attach a Department when one is chosen; omitting it marks the Course
    // university-wide (the server treats an absent owner as null — ADR-0010).
    if (departmentId !== UNIVERSITY_WIDE) {
      data.department_id = Number(departmentId)
    }

    if (isEdit && course?.id !== undefined) {
      update.mutate({ courseId: course.id, data }, { onSuccess: onSaved })
    } else {
      create.mutate({ data }, { onSuccess: onSaved })
    }
  }

  const chFilled = (value: string) => value.trim() !== '' && Number(value) >= 0
  const isComplete =
    code.trim() !== '' &&
    nameEn.trim() !== '' &&
    nameAr.trim() !== '' &&
    level !== '' &&
    chFilled(lectureCh) &&
    chFilled(tutorialCh) &&
    chFilled(labCh) &&
    chFilled(totalCh)

  const componentSum = [lectureCh, tutorialCh, labCh].reduce(
    (sum, value) => sum + (Number(value) || 0),
    0,
  )
  const total = Number(totalCh) || 0
  const balanced = componentSum === total

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle>{isEdit ? t('courses.edit') : t('courses.new')}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ mt: 1 }}>
          {isError && <Alert severity="error">{t('courses.saveError')}</Alert>}
          <TextField
            select
            label={t('courses.department')}
            value={departmentId}
            onChange={(event) => setDepartmentId(event.target.value)}
            fullWidth
          >
            <MenuItem value={UNIVERSITY_WIDE}>{t('courses.universityWide')}</MenuItem>
            {departments.map((department) => (
              <MenuItem key={department.id} value={String(department.id)}>
                {pickLocaleName(department, i18n.language, department.code ?? '')}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            label={t('courses.code')}
            value={code}
            onChange={(event) => setCode(event.target.value)}
            required
            fullWidth
          />
          <TextField
            label={t('courses.nameEn')}
            value={nameEn}
            onChange={(event) => setNameEn(event.target.value)}
            required
            fullWidth
          />
          <TextField
            label={t('courses.nameAr')}
            value={nameAr}
            onChange={(event) => setNameAr(event.target.value)}
            required
            fullWidth
          />
          <TextField
            select
            label={t('courses.level')}
            value={level}
            onChange={(event) => setLevel(event.target.value)}
            required
            fullWidth
          >
            {COURSE_LEVELS.map((value) => (
              <MenuItem key={value} value={String(value)}>
                {value}
              </MenuItem>
            ))}
          </TextField>
          <Typography variant="subtitle2" color="text.secondary">
            {t('courses.creditBreakdown')}
          </Typography>
          <Stack direction="row" spacing={2}>
            <TextField
              type="number"
              label={t('courses.lectureCh')}
              value={lectureCh}
              onChange={(event) => setLectureCh(event.target.value)}
              slotProps={{ htmlInput: { min: 0 } }}
              required
              fullWidth
            />
            <TextField
              type="number"
              label={t('courses.tutorialCh')}
              value={tutorialCh}
              onChange={(event) => setTutorialCh(event.target.value)}
              slotProps={{ htmlInput: { min: 0 } }}
              required
              fullWidth
            />
            <TextField
              type="number"
              label={t('courses.labCh')}
              value={labCh}
              onChange={(event) => setLabCh(event.target.value)}
              slotProps={{ htmlInput: { min: 0 } }}
              required
              fullWidth
            />
            <TextField
              type="number"
              label={t('courses.totalCh')}
              value={totalCh}
              onChange={(event) => setTotalCh(event.target.value)}
              slotProps={{ htmlInput: { min: 0 } }}
              required
              fullWidth
            />
          </Stack>
          <Typography variant="body2" color={balanced ? 'text.secondary' : 'error'}>
            {balanced
              ? t('courses.creditBalanced', { sum: componentSum })
              : t('courses.creditUnbalanced', { sum: componentSum, total })}
          </Typography>
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={isSaving}>
          {t('courses.cancel')}
        </Button>
        <Button
          onClick={handleSubmit}
          variant="contained"
          disabled={isSaving || !isComplete || !balanced}
        >
          {t('courses.save')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
