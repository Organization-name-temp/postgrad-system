import { useState } from 'react'
import {
  Alert,
  Button,
  Chip,
  CircularProgress,
  IconButton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tooltip,
  Typography,
} from '@mui/material'
import AddIcon from '@mui/icons-material/Add'
import EditIcon from '@mui/icons-material/Edit'
import DeleteIcon from '@mui/icons-material/Delete'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListInterviewSlotsQueryKey,
  exportInterviewSlots,
  useDeleteAnInterviewSlot,
  useListInterviewSlots,
} from '../../../shared/api/generated/endpoints'
import type { ListInterviewSlots200DataItem } from '../../../shared/api/generated/endpoints'
import { formatSlotWhen, slotWhere } from './slotDisplay'
import { FilterBar } from '../../../shared/ui/FilterBar'
import { DataToolbar } from '../../../shared/ui/DataToolbar'
import { useCollectionQuery } from '../../../shared/ui/useCollectionQuery'
import { downloadBlob } from '../../../shared/downloadBlob'
import { SlotFormDialog } from './SlotFormDialog'

/**
 * The interview-slot pool screen (features/admissions). Student Affairs (holding
 * `interview.schedule`) manage the appointments approved applicants can be scheduled
 * into — creating them, and editing/deleting only the still-open ones (the API refuses
 * to change a booked slot). Each row shows when/where, the interviewer, and whether the
 * slot is open or taken.
 */
export function SlotsPage() {
  const { t, i18n } = useTranslation('admissions')
  const queryClient = useQueryClient()
  const collection = useCollectionQuery()
  const params = {
    ...collection.params,
    duration_minutes_min: collection.searchParams.has('duration_minutes_min')
      ? Number(collection.searchParams.get('duration_minutes_min'))
      : undefined,
    duration_minutes_max: collection.searchParams.has('duration_minutes_max')
      ? Number(collection.searchParams.get('duration_minutes_max'))
      : undefined,
  }
  const { data, isPending, isError } = useListInterviewSlots(params)
  const slots = data?.data ?? []

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<ListInterviewSlots200DataItem | undefined>(undefined)

  const remove = useDeleteAnInterviewSlot()

  const openCreate = () => {
    setEditing(undefined)
    setDialogOpen(true)
  }
  const openEdit = (slot: ListInterviewSlots200DataItem) => {
    setEditing(slot)
    setDialogOpen(true)
  }
  const onDelete = (slot: ListInterviewSlots200DataItem) => {
    if (slot.id === undefined) {
      return
    }
    remove.mutate(
      { interviewSlotId: slot.id },
      {
        onSuccess: () =>
          queryClient.invalidateQueries({ queryKey: getListInterviewSlotsQueryKey() }),
      },
    )
  }

  return (
    <>
      <Stack
        direction="row"
        sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 3, gap: 2 }}
      >
        <Typography variant="h4" component="h1">
          {t('interviews.slots.title')}
        </Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={openCreate}>
          {t('interviews.slots.new')}
        </Button>
      </Stack>
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('interviews.slots.intro')}
      </Typography>

      <FilterBar
        values={collection.params}
        onChange={collection.setCriteria}
        fields={[
          { key: 'q', label: t('interviews.slots.filters.search') },
          { key: 'interviewer_id', label: t('interviews.slots.filters.interviewer') },
          {
            key: 'mode',
            label: t('interviews.slots.mode'),
            options: [
              { value: '', label: t('interviews.filters.allModes') },
              { value: 'in_person', label: t('interviews.modes.in_person') },
              { value: 'virtual', label: t('interviews.modes.virtual') },
            ],
          },
          {
            key: 'is_open',
            label: t('interviews.slots.columns.state'),
            options: [
              { value: '', label: t('interviews.slots.filters.allAvailability') },
              { value: 'true', label: t('interviews.slots.open') },
              { value: 'false', label: t('interviews.slots.taken') },
            ],
          },
          { key: 'scheduled_at_min', label: t('interviews.filters.from'), type: 'date' },
          { key: 'scheduled_at_max', label: t('interviews.filters.to'), type: 'date' },
          {
            key: 'duration_minutes_min',
            label: t('interviews.slots.filters.minDuration'),
            type: 'number',
          },
          {
            key: 'duration_minutes_max',
            label: t('interviews.slots.filters.maxDuration'),
            type: 'number',
          },
          { key: 'sort', label: t('screening.filters.sort') },
        ]}
      />
      {data?.meta && (
        <DataToolbar
          total={data.meta.total ?? 0}
          page={data.meta.current_page ?? params.page}
          lastPage={data.meta.last_page ?? 1}
          onPageChange={collection.setPage}
          onExport={async () =>
            downloadBlob(await exportInterviewSlots(params), 'interview-slots.xlsx')
          }
        />
      )}

      {isPending && <CircularProgress aria-label={t('interviews.slots.loading')} sx={{ mt: 2 }} />}
      {isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('interviews.slots.loadError')}
        </Alert>
      )}
      {remove.isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('interviews.slots.deleteError')}
        </Alert>
      )}

      {!isPending && !isError && slots.length === 0 && (
        <Typography color="text.secondary" sx={{ mt: 2 }}>
          {t('interviews.slots.empty')}
        </Typography>
      )}

      {slots.length > 0 && (
        <TableContainer sx={{ mt: 2 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('interviews.slots.columns.when')}</TableCell>
                <TableCell>{t('interviews.slots.columns.duration')}</TableCell>
                <TableCell>{t('interviews.slots.columns.interviewer')}</TableCell>
                <TableCell>{t('interviews.slots.columns.where')}</TableCell>
                <TableCell>{t('interviews.slots.columns.state')}</TableCell>
                <TableCell align="right">{t('interviews.slots.columns.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {slots.map((slot) => (
                <TableRow key={slot.id} hover>
                  <TableCell>{formatSlotWhen(slot.scheduled_at, i18n.language)}</TableCell>
                  <TableCell>
                    {t('interviews.minutes', { count: slot.duration_minutes ?? 0 })}
                  </TableCell>
                  <TableCell>{slot.interviewer?.name ?? '—'}</TableCell>
                  <TableCell>{slotWhere(slot) ?? '—'}</TableCell>
                  <TableCell>
                    <Chip
                      size="small"
                      color={slot.is_open ? 'success' : 'default'}
                      label={
                        slot.is_open ? t('interviews.slots.open') : t('interviews.slots.taken')
                      }
                    />
                  </TableCell>
                  <TableCell align="right">
                    <Tooltip title={t('interviews.slots.editAction')}>
                      <span>
                        <IconButton
                          size="small"
                          onClick={() => openEdit(slot)}
                          disabled={!slot.is_open}
                          aria-label={t('interviews.slots.editAction')}
                        >
                          <EditIcon fontSize="small" />
                        </IconButton>
                      </span>
                    </Tooltip>
                    <Tooltip title={t('interviews.slots.deleteAction')}>
                      <span>
                        <IconButton
                          size="small"
                          color="error"
                          onClick={() => onDelete(slot)}
                          disabled={!slot.is_open || remove.isPending}
                          aria-label={t('interviews.slots.deleteAction')}
                        >
                          <DeleteIcon fontSize="small" />
                        </IconButton>
                      </span>
                    </Tooltip>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      <SlotFormDialog open={dialogOpen} onClose={() => setDialogOpen(false)} slot={editing} />
    </>
  )
}
