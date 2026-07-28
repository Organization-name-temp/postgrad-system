import { useState } from 'react'
import {
  Alert,
  AlertTitle,
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  IconButton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlined'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListDepartmentHeadDepartmentAssignmentsQueryKey,
  useListDepartmentHeadDepartmentAssignments,
  useListDepartments,
  useUnassignADepartmentHeadFromAScope,
} from '../../../shared/api/generated/endpoints'
import type { ListDepartmentHeadDepartmentAssignments200DataItem } from '../../../shared/api/generated/endpoints'
import { DepartmentHeadFormDialog } from './DepartmentHeadFormDialog'
import { nameById } from './nameById'
import { uncoveredScopes } from './uncoveredScopes'

/**
 * The Department-Head↔Department assignment admin screen (phase 4, ticket 02), beside
 * the Dean assignments and behind the same role gate: visible only to System/Super
 * Admins in the nav, and the API refuses every request from a non-admin (403,
 * ADR-0008). Department names are joined from the Catalog departments list, since an
 * assignment carries only the opaque department id (ADR-0012). Unassigning is
 * destructive, so it goes through a confirmation dialog naming the Head and the scope
 * (design-system.md §9).
 *
 * Two things distinguish it from the Dean screen, both from the ticket:
 *
 * - **The university-wide Preparatory scope is a named row**, never a blank Department
 *   cell. It is a real scope — the PRE401–404 Courses belong to no Department — so a
 *   reader must not have to work out what an empty cell means.
 * - **Scopes with no Head are surfaced as their own visible state.** A Department
 *   nobody reviews is where signed Grade Sheets would strand, and it is invisible in a
 *   list of assignments precisely because it has no row there — so it is called out
 *   above the table instead of left to be noticed by absence.
 */
export function DepartmentHeadsPage() {
  const { t, i18n } = useTranslation('admin')
  const queryClient = useQueryClient()

  const { data, isPending, isError } = useListDepartmentHeadDepartmentAssignments()
  const assignments = data?.data ?? []

  // The Catalog departments list is the *denominator* of the coverage warning, not just
  // a source of labels. An empty one makes every Department look covered, so a load
  // that is pending or failed must suppress the warning and say so — reporting "every
  // Department has a Head" because the list never arrived is the one wrong answer this
  // screen must not give.
  const departmentsQuery = useListDepartments()
  const departments = departmentsQuery.data?.data ?? []
  const coverageKnown = departmentsQuery.isSuccess

  const [dialogOpen, setDialogOpen] = useState(false)
  const [confirming, setConfirming] =
    useState<ListDepartmentHeadDepartmentAssignments200DataItem | null>(null)
  const unassign = useUnassignADepartmentHeadFromAScope()

  const universityWideLabel = t('departmentHeads.universityWide')

  /** The scope a row covers, always named: a Department, or the university-wide scope. */
  const scopeLabel = (assignment: ListDepartmentHeadDepartmentAssignments200DataItem): string =>
    assignment.university_wide
      ? universityWideLabel
      : nameById(
          departments,
          assignment.department_id,
          i18n.language,
          t('departmentHeads.unknownDepartment', { id: assignment.department_id }),
        )

  const uncovered = uncoveredScopes(departments, assignments, i18n.language, universityWideLabel)

  const confirmUnassign = () => {
    if (confirming?.id === undefined) {
      return
    }
    unassign.mutate(
      { assignmentId: confirming.id },
      {
        onSuccess: () => {
          void queryClient.invalidateQueries({
            queryKey: getListDepartmentHeadDepartmentAssignmentsQueryKey(),
          })
          setConfirming(null)
        },
      },
    )
  }

  return (
    <>
      <Stack
        direction="row"
        sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 1, gap: 2 }}
      >
        <Typography variant="h4" component="h1">
          {t('departmentHeads.title')}
        </Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={() => setDialogOpen(true)}>
          {t('departmentHeads.new')}
        </Button>
      </Stack>
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('departmentHeads.intro')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('departmentHeads.loading')} sx={{ mt: 2 }} />}
      {isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('departmentHeads.loadError')}
        </Alert>
      )}
      {unassign.isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('departmentHeads.unassignError')}
        </Alert>
      )}
      {departmentsQuery.isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('departmentHeads.coverageUnknown')}
        </Alert>
      )}

      {!isPending && !isError && coverageKnown && uncovered.length > 0 && (
        <Alert severity="warning" sx={{ mt: 2 }}>
          <AlertTitle>{t('departmentHeads.uncoveredTitle')}</AlertTitle>
          {t('departmentHeads.uncoveredBody')}
          {/* The browser's own list indent is already logical, so it flips in RTL
              without an override. */}
          <Typography component="ul" variant="body2" sx={{ mt: 1, mb: 0 }}>
            {uncovered.map((scope) => (
              <li key={scope.key}>{scope.label}</li>
            ))}
          </Typography>
        </Alert>
      )}

      {!isPending && !isError && assignments.length === 0 && (
        <Typography color="text.secondary" sx={{ mt: 2 }}>
          {t('departmentHeads.empty')}
        </Typography>
      )}

      {assignments.length > 0 && (
        <TableContainer sx={{ mt: 2 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('departmentHeads.head')}</TableCell>
                <TableCell>{t('departmentHeads.scope')}</TableCell>
                <TableCell align="right">{t('departmentHeads.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {assignments.map((assignment) => (
                <TableRow key={assignment.id} hover>
                  <TableCell>{assignment.head_name}</TableCell>
                  <TableCell>{scopeLabel(assignment)}</TableCell>
                  <TableCell align="right">
                    <IconButton
                      size="small"
                      color="error"
                      aria-label={t('departmentHeads.unassignAria', {
                        head: assignment.head_name,
                        scope: scopeLabel(assignment),
                      })}
                      onClick={() => setConfirming(assignment)}
                    >
                      <DeleteOutlineIcon fontSize="small" />
                    </IconButton>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      <DepartmentHeadFormDialog open={dialogOpen} onClose={() => setDialogOpen(false)} />

      <Dialog open={confirming !== null} onClose={() => setConfirming(null)}>
        <DialogTitle>{t('departmentHeads.unassignTitle')}</DialogTitle>
        <DialogContent>
          <DialogContentText>
            {confirming &&
              t('departmentHeads.unassignBody', {
                head: confirming.head_name,
                scope: scopeLabel(confirming),
              })}
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirming(null)} disabled={unassign.isPending}>
            {t('departmentHeads.cancel')}
          </Button>
          <Button
            variant="contained"
            color="error"
            startIcon={<DeleteOutlineIcon />}
            onClick={confirmUnassign}
            disabled={unassign.isPending}
          >
            {unassign.isPending ? t('departmentHeads.unassigning') : t('departmentHeads.unassign')}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  )
}
