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
import { useListAdmissionCycles } from '../../../shared/api/generated/endpoints'
import type { ListAdmissionCycles200DataItem } from '../../../shared/api/generated/endpoints'
import { usePermissions } from '../../../shared/access/usePermissions'
import { PERMISSION } from '../../../shared/access/permissions'
import { CycleRow } from './CycleRow'
import { CycleFormDialog } from './CycleFormDialog'

/**
 * The Admission Cycles admin screen (features/admin). The whole surface is gated
 * on calendar.manage (Student Affairs / Dean) — the API enforces the same on
 * every request (ADR-0008). Managers list the intake windows and create / edit /
 * activate / deactivate them; whether a cycle is "open" is the server's derived
 * flag, shown as a StatusChip, never edited directly (ADR-0012, ticket 01).
 */
export function CyclesPage() {
  const { t } = useTranslation('admin')
  const { hasPermission } = usePermissions()
  const canManage = hasPermission(PERMISSION.manageCalendar)

  const { data, isPending, isError } = useListAdmissionCycles()
  const cycles = data?.data ?? []

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<ListAdmissionCycles200DataItem | undefined>(undefined)

  const openCreate = () => {
    setEditing(undefined)
    setDialogOpen(true)
  }
  const openEdit = (cycle: ListAdmissionCycles200DataItem) => {
    setEditing(cycle)
    setDialogOpen(true)
  }

  return (
    <>
      <Stack
        direction="row"
        sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 3, gap: 2 }}
      >
        <Typography variant="h4" component="h1">
          {t('cycles.title')}
        </Typography>
        {canManage && (
          <Button variant="contained" startIcon={<AddIcon />} onClick={openCreate}>
            {t('cycles.new')}
          </Button>
        )}
      </Stack>

      {isPending && <CircularProgress aria-label={t('cycles.loading')} />}
      {isError && <Alert severity="error">{t('cycles.loadError')}</Alert>}

      {!isPending && !isError && cycles.length === 0 && (
        <Typography color="text.secondary">{t('cycles.empty')}</Typography>
      )}

      {cycles.length > 0 && (
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('cycles.name')}</TableCell>
                <TableCell>{t('cycles.intakeTerm')}</TableCell>
                <TableCell>{t('cycles.year')}</TableCell>
                <TableCell>{t('cycles.window')}</TableCell>
                <TableCell>{t('cycles.statusHeader')}</TableCell>
                {canManage && <TableCell align="right">{t('cycles.actions')}</TableCell>}
              </TableRow>
            </TableHead>
            <TableBody>
              {cycles.map((cycle) => (
                <CycleRow key={cycle.id} cycle={cycle} canManage={canManage} onEdit={openEdit} />
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      <CycleFormDialog open={dialogOpen} onClose={() => setDialogOpen(false)} cycle={editing} />
    </>
  )
}
