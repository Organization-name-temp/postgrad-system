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
  getListDeanFacultyAssignmentsQueryKey,
  useAssignADeanToAFaculty,
  useListAssignableDeans,
  useListFaculties,
} from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'

interface AssignmentFormDialogProps {
  open: boolean
  onClose: () => void
}

/**
 * Assign a Dean to a Faculty (ticket 08). Two pickers — a Dean (R07 users from the
 * admin deans list) and a Faculty (the Catalog faculties list, which an admin can
 * read since admins hold every permission) — POST one routing row. On success it
 * invalidates the assignments list so the table refetches, then closes. The server
 * re-validates (must be a Dean, pair must be unique); a failure surfaces as an inline
 * alert — the API stays the authority (ADR-0008).
 */
export function AssignmentFormDialog({ open, onClose }: AssignmentFormDialogProps) {
  const { t, i18n } = useTranslation('admin')
  const queryClient = useQueryClient()

  const [deanId, setDeanId] = useState('')
  const [facultyId, setFacultyId] = useState('')

  useEffect(() => {
    if (open) {
      setDeanId('')
      setFacultyId('')
    }
  }, [open])

  const deans = useListAssignableDeans({ query: { enabled: open } }).data?.data ?? []
  const faculties = useListFaculties({ query: { enabled: open } }).data?.data ?? []

  const assign = useAssignADeanToAFaculty()

  const handleSubmit = () => {
    assign.mutate(
      { data: { user_id: Number(deanId), faculty_id: Number(facultyId) } },
      {
        onSuccess: () => {
          void queryClient.invalidateQueries({ queryKey: getListDeanFacultyAssignmentsQueryKey() })
          onClose()
        },
      },
    )
  }

  const isComplete = deanId !== '' && facultyId !== ''

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle>{t('assignments.dialogTitle')}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ mt: 1 }}>
          {assign.isError && <Alert severity="error">{t('assignments.saveError')}</Alert>}
          <TextField
            select
            label={t('assignments.selectDean')}
            value={deanId}
            onChange={(event) => setDeanId(event.target.value)}
            required
            fullWidth
          >
            {deans.map((dean) => (
              <MenuItem key={dean.id} value={dean.id}>
                {dean.name}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            select
            label={t('assignments.selectFaculty')}
            value={facultyId}
            onChange={(event) => setFacultyId(event.target.value)}
            required
            fullWidth
          >
            {faculties.map((faculty) => (
              <MenuItem key={faculty.id} value={faculty.id}>
                {pickLocaleName(faculty, i18n.language, faculty.code ?? '')}
              </MenuItem>
            ))}
          </TextField>
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={assign.isPending}>
          {t('assignments.cancel')}
        </Button>
        <Button
          onClick={handleSubmit}
          variant="contained"
          disabled={assign.isPending || !isComplete}
        >
          {assign.isPending ? t('assignments.saving') : t('assignments.save')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
