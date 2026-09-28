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
import {
  exportAdmissionCycles,
  useListAdmissionCycles,
} from '../../../shared/api/generated/endpoints'
import type { ListAdmissionCycles200DataItem } from '../../../shared/api/generated/endpoints'
import { usePermissions } from '../../../shared/access/usePermissions'
import { PERMISSION } from '../../../shared/access/permissions'
import { CycleRow } from './CycleRow'
import { CycleFormDialog } from './CycleFormDialog'
import { FilterBar } from '../../../shared/ui/FilterBar'
import { DataToolbar } from '../../../shared/ui/DataToolbar'
import { useCollectionQuery } from '../../../shared/ui/useCollectionQuery'
import { downloadBlob } from '../../../shared/downloadBlob'

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

  const collection = useCollectionQuery()
  const params = {
    ...collection.params,
    year_min: collection.searchParams.has('year_min')
      ? Number(collection.searchParams.get('year_min'))
      : undefined,
    year_max: collection.searchParams.has('year_max')
      ? Number(collection.searchParams.get('year_max'))
      : undefined,
  }
  const { data, isPending, isError } = useListAdmissionCycles(params)
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

      <FilterBar
        values={collection.params}
        onChange={collection.setCriteria}
        fields={[
          { key: 'q', label: t('cycles.filters.search') },
          { key: 'year', label: t('cycles.year') },
          { key: 'year_min', label: t('cycles.filters.minYear'), type: 'number' },
          { key: 'year_max', label: t('cycles.filters.maxYear'), type: 'number' },
          {
            key: 'intake_term',
            label: t('cycles.intakeTerm'),
            options: [
              { value: '', label: t('cycles.filters.all') },
              { value: 'Fall', label: t('cycles.terms.Fall') },
              { value: 'Spring', label: t('cycles.terms.Spring') },
            ],
          },
          {
            key: 'is_active',
            label: t('cycles.filters.activeState'),
            options: [
              { value: '', label: t('cycles.filters.all') },
              { value: 'true', label: t('cycles.filters.active') },
              { value: 'false', label: t('cycles.status.inactive') },
            ],
          },
          {
            key: 'is_open',
            label: t('cycles.filters.windowState'),
            options: [
              { value: '', label: t('cycles.filters.all') },
              { value: 'true', label: t('cycles.status.open') },
              { value: 'false', label: t('cycles.filters.notOpen') },
            ],
          },
          {
            key: 'sort',
            label: t('cycles.filters.sort'),
            options: [
              { value: '', label: t('cycles.filters.newest') },
              { value: '-year', label: t('cycles.filters.newest') },
              { value: 'year', label: t('cycles.filters.oldest') },
              { value: 'name_en', label: t('cycles.filters.nameEnglish') },
              { value: 'name_ar', label: t('cycles.filters.nameArabic') },
            ],
          },
        ]}
      />
      {data?.meta && !isError && (
        <DataToolbar
          total={data.meta.total ?? 0}
          page={data.meta.current_page ?? params.page}
          lastPage={data.meta.last_page ?? 1}
          onPageChange={collection.setPage}
          onExport={async () =>
            downloadBlob(await exportAdmissionCycles(params), 'admission-cycles.xlsx')
          }
        />
      )}

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
