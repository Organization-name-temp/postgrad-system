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
  getListDepartmentHeadDepartmentAssignmentsQueryKey,
  useAssignADepartmentHeadToAScope,
  useListAssignableDepartmentHeads,
  useListDepartments,
} from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'

interface DepartmentHeadFormDialogProps {
  open: boolean
  onClose: () => void
}

/**
 * The sentinel the scope picker uses for the university-wide Preparatory scope, which
 * the API models as a `null` department. It is deliberately **not** the empty string:
 * empty means "nothing picked yet", and the whole point of this screen is that the
 * university-wide scope is a choice an administrator makes rather than a field they
 * leave blank (ticket 02, ADR-0023).
 */
const UNIVERSITY_WIDE = 'university-wide'

/**
 * Assign a Department Head to a scope (phase 4, ticket 02). Two pickers — a Head (R06
 * users from the admin department-heads list) and a scope (the Catalog departments
 * list, plus the named university-wide option) — POST one routing row. On success it
 * invalidates the assignments list so the table refetches, then closes. The server
 * re-validates (must be a Head, pair must be unique); a failure surfaces as an inline
 * alert — the API stays the authority (ADR-0008).
 */
export function DepartmentHeadFormDialog({ open, onClose }: DepartmentHeadFormDialogProps) {
  const { t, i18n } = useTranslation('admin')
  const queryClient = useQueryClient()

  const [headId, setHeadId] = useState('')
  const [scope, setScope] = useState('')

  useEffect(() => {
    if (open) {
      setHeadId('')
      setScope('')
    }
  }, [open])

  const heads = useListAssignableDepartmentHeads({ query: { enabled: open } }).data?.data ?? []
  const departments = useListDepartments(undefined, { query: { enabled: open } }).data?.data ?? []

  const assign = useAssignADepartmentHeadToAScope()

  const handleSubmit = () => {
    assign.mutate(
      {
        data: {
          user_id: Number(headId),
          // The API takes the scope as a nullable department id, and sends it always:
          // an omitted key is a 422, so NULL can only ever mean a chosen scope.
          department_id: scope === UNIVERSITY_WIDE ? null : Number(scope),
        },
      },
      {
        onSuccess: () => {
          void queryClient.invalidateQueries({
            queryKey: getListDepartmentHeadDepartmentAssignmentsQueryKey(),
          })
          onClose()
        },
      },
    )
  }

  const isComplete = headId !== '' && scope !== ''

  return (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
      <DialogTitle>{t('departmentHeads.dialogTitle')}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ mt: 1 }}>
          {assign.isError && <Alert severity="error">{t('departmentHeads.saveError')}</Alert>}
          <TextField
            select
            label={t('departmentHeads.selectHead')}
            value={headId}
            onChange={(event) => setHeadId(event.target.value)}
            required
            fullWidth
          >
            {heads.map((head) => (
              <MenuItem key={head.id} value={head.id}>
                {head.name}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            select
            label={t('departmentHeads.selectScope')}
            value={scope}
            onChange={(event) => setScope(event.target.value)}
            helperText={t('departmentHeads.scopeHelp')}
            required
            fullWidth
          >
            {/* The university-wide scope leads the list as a named option — a peer of
                the Departments, not one of them. It is set apart by its label alone: a
                <Divider> here would put an `hr role="separator"` among the listbox's
                `role="option"` children, which is an aria-required-children violation. */}
            <MenuItem value={UNIVERSITY_WIDE}>{t('departmentHeads.universityWide')}</MenuItem>
            {departments.map((department) => (
              <MenuItem key={department.id} value={department.id}>
                {pickLocaleName(department, i18n.language, department.code ?? '')}
              </MenuItem>
            ))}
          </TextField>
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={assign.isPending}>
          {t('departmentHeads.cancel')}
        </Button>
        <Button
          onClick={handleSubmit}
          variant="contained"
          disabled={assign.isPending || !isComplete}
        >
          {assign.isPending ? t('departmentHeads.saving') : t('departmentHeads.save')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
