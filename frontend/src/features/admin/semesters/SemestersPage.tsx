import { useState } from 'react'
import {
  Alert,
  Button,
  CircularProgress,
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
import { useTranslation } from 'react-i18next'
import { useListSemesters } from '../../../shared/api/generated/endpoints'
import type { ListSemesters200DataItem } from '../../../shared/api/generated/endpoints'
import { usePermissions } from '../../../shared/access/usePermissions'
import { PERMISSION } from '../../../shared/access/permissions'
import { SemesterRow } from './SemesterRow'
import { SemesterFormDialog } from './SemesterFormDialog'

/**
 * The Semesters admin screen (features/admin, calendar nav section beside Cycles).
 * Reading is open to any authenticated user; creating and editing is gated on
 * calendar.manage (Student Affairs / Dean / SysAdmin) — the API enforces the same
 * on every request (ADR-0008). A Semester's state (upcoming / registration open /
 * running / ended) is derived from its dates and shown as a StatusChip; Semesters
 * are edited in place, never deleted (ADR-0018, ticket 01).
 */
export function SemestersPage() {
  const { t } = useTranslation('admin')
  const { hasPermission } = usePermissions()
  const canManage = hasPermission(PERMISSION.manageCalendar)

  const { data, isPending, isError } = useListSemesters()
  const semesters = data?.data ?? []

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<ListSemesters200DataItem | undefined>(undefined)

  const openCreate = () => {
    setEditing(undefined)
    setDialogOpen(true)
  }
  const openEdit = (semester: ListSemesters200DataItem) => {
    setEditing(semester)
    setDialogOpen(true)
  }

  return (
    <>
      <Stack
        direction="row"
        sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 3, gap: 2 }}
      >
        <Typography variant="h4" component="h1">
          {t('semesters.title')}
        </Typography>
        {canManage && (
          <Button variant="contained" startIcon={<AddIcon />} onClick={openCreate}>
            {t('semesters.new')}
          </Button>
        )}
      </Stack>

      {isPending && <CircularProgress aria-label={t('semesters.loading')} />}
      {isError && <Alert severity="error">{t('semesters.loadError')}</Alert>}

      {!isPending && !isError && semesters.length === 0 && (
        <Typography color="text.secondary">{t('semesters.empty')}</Typography>
      )}

      {semesters.length > 0 && (
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('semesters.name')}</TableCell>
                <TableCell>{t('semesters.term')}</TableCell>
                <TableCell>{t('semesters.year')}</TableCell>
                <TableCell>{t('semesters.semesterWindow')}</TableCell>
                <TableCell>{t('semesters.registrationWindow')}</TableCell>
                <TableCell>{t('semesters.statusHeader')}</TableCell>
                {canManage && <TableCell align="right">{t('semesters.actions')}</TableCell>}
              </TableRow>
            </TableHead>
            <TableBody>
              {semesters.map((semester) => (
                <SemesterRow
                  key={semester.id}
                  semester={semester}
                  canManage={canManage}
                  onEdit={openEdit}
                />
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      <SemesterFormDialog
        open={dialogOpen}
        onClose={() => setDialogOpen(false)}
        semester={editing}
      />
    </>
  )
}
