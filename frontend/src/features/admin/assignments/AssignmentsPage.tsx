import { useState } from 'react'
import {
  Alert,
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
  getListDeanFacultyAssignmentsQueryKey,
  useListDeanFacultyAssignments,
  useListFaculties,
  useUnassignADeanFromAFaculty,
} from '../../../shared/api/generated/endpoints'
import type { ListDeanFacultyAssignments200DataItem } from '../../../shared/api/generated/endpoints'
import { AssignmentFormDialog } from './AssignmentFormDialog'
import { facultyName } from './facultyName'

/**
 * The Dean↔Faculty assignment admin screen (features/admin) — the first role-gated
 * surface. Visible only to System/Super Admins in the nav, and the API refuses every
 * request from a non-admin (403, ADR-0008). An administrator sees each Dean paired
 * with the faculty they review, assigns a new pair, or unassigns one. Faculty names
 * are joined from the Catalog faculties list, since the assignment carries only the
 * opaque faculty id (ADR-0012). Unassigning is destructive, so it goes through a
 * confirmation dialog naming the Dean and faculty (design-system.md §9).
 */
export function AssignmentsPage() {
  const { t, i18n } = useTranslation('admin')
  const queryClient = useQueryClient()

  const { data, isPending, isError } = useListDeanFacultyAssignments()
  const assignments = data?.data ?? []
  const faculties = useListFaculties().data?.data ?? []

  const [dialogOpen, setDialogOpen] = useState(false)
  const [confirming, setConfirming] = useState<ListDeanFacultyAssignments200DataItem | null>(null)
  const unassign = useUnassignADeanFromAFaculty()

  const facultyLabel = (facultyId: number | undefined): string =>
    facultyName(
      faculties,
      facultyId,
      i18n.language,
      t('assignments.unknownFaculty', { id: facultyId }),
    )

  const confirmUnassign = () => {
    if (confirming?.id === undefined) {
      return
    }
    unassign.mutate(
      { assignmentId: confirming.id },
      {
        onSuccess: () => {
          void queryClient.invalidateQueries({ queryKey: getListDeanFacultyAssignmentsQueryKey() })
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
          {t('assignments.title')}
        </Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={() => setDialogOpen(true)}>
          {t('assignments.new')}
        </Button>
      </Stack>
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('assignments.intro')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('assignments.loading')} sx={{ mt: 2 }} />}
      {isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('assignments.loadError')}
        </Alert>
      )}
      {unassign.isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('assignments.unassignError')}
        </Alert>
      )}

      {!isPending && !isError && assignments.length === 0 && (
        <Typography color="text.secondary" sx={{ mt: 2 }}>
          {t('assignments.empty')}
        </Typography>
      )}

      {assignments.length > 0 && (
        <TableContainer sx={{ mt: 2 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('assignments.dean')}</TableCell>
                <TableCell>{t('assignments.faculty')}</TableCell>
                <TableCell align="right">{t('assignments.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {assignments.map((assignment) => (
                <TableRow key={assignment.id} hover>
                  <TableCell>{assignment.dean_name}</TableCell>
                  <TableCell>{facultyLabel(assignment.faculty_id)}</TableCell>
                  <TableCell align="right">
                    <IconButton
                      size="small"
                      color="error"
                      aria-label={t('assignments.unassignAria', { dean: assignment.dean_name })}
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

      <AssignmentFormDialog open={dialogOpen} onClose={() => setDialogOpen(false)} />

      <Dialog open={confirming !== null} onClose={() => setConfirming(null)}>
        <DialogTitle>{t('assignments.unassignTitle')}</DialogTitle>
        <DialogContent>
          <DialogContentText>
            {confirming &&
              t('assignments.unassignBody', {
                dean: confirming.dean_name,
                faculty: facultyLabel(confirming.faculty_id),
              })}
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirming(null)} disabled={unassign.isPending}>
            {t('assignments.cancel')}
          </Button>
          <Button
            variant="contained"
            color="error"
            startIcon={<DeleteOutlineIcon />}
            onClick={confirmUnassign}
            disabled={unassign.isPending}
          >
            {unassign.isPending ? t('assignments.unassigning') : t('assignments.unassign')}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  )
}
