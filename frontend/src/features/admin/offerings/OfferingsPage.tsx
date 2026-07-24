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
  Link as MuiLink,
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
import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlined'
import { Link as RouterLink, useParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListASemestersCourseOfferingsQueryKey,
  useDeleteACourseOffering,
  useListASemestersCourseOfferings,
  useShowASemester,
} from '../../../shared/api/generated/endpoints'
import type { ListASemestersCourseOfferings200DataItem } from '../../../shared/api/generated/endpoints'
import { usePermissions } from '../../../shared/access/usePermissions'
import { PERMISSION } from '../../../shared/access/permissions'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { OfferingRow } from './OfferingRow'
import { OfferingFormDialog } from './OfferingFormDialog'

/**
 * The per-Semester Course Offerings admin screen (features/admin, calendar section):
 * "what runs this semester." Reachable from the Semesters list; all CRUD is gated on
 * calendar.manage and the API re-enforces it (ADR-0008). An administrator lists the
 * Semester's Offerings, creates one (course + instructor + capacity + weekly meetings),
 * edits it, or removes one — the API refuses removal once the Offering is referenced by
 * an enrollment or session (ADR-0017).
 */
export function OfferingsPage() {
  const { t, i18n } = useTranslation('admin')
  const { hasPermission } = usePermissions()
  const canManage = hasPermission(PERMISSION.manageCalendar)
  const queryClient = useQueryClient()

  const { semesterId: semesterIdParam } = useParams()
  const semesterId = Number(semesterIdParam)

  const semester = useShowASemester(semesterId).data?.data
  const { data, isPending, isError } = useListASemestersCourseOfferings(semesterId)
  const offerings = data?.data ?? []

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<ListASemestersCourseOfferings200DataItem | undefined>(
    undefined,
  )
  const [confirming, setConfirming] = useState<ListASemestersCourseOfferings200DataItem | null>(
    null,
  )
  const remove = useDeleteACourseOffering()

  const openCreate = () => {
    setEditing(undefined)
    setDialogOpen(true)
  }
  const openEdit = (offering: ListASemestersCourseOfferings200DataItem) => {
    setEditing(offering)
    setDialogOpen(true)
  }

  const confirmDelete = () => {
    if (confirming?.id === undefined) {
      return
    }
    remove.mutate(
      { offeringId: confirming.id },
      {
        onSuccess: () => {
          void queryClient.invalidateQueries({
            queryKey: getListASemestersCourseOfferingsQueryKey(semesterId),
          })
          setConfirming(null)
        },
      },
    )
  }

  const semesterName = semester ? pickLocaleName(semester, i18n.language) : ''

  return (
    <>
      <MuiLink
        component={RouterLink}
        to="/admin/semesters"
        sx={{ display: 'inline-flex', alignItems: 'center', gap: 0.5, mb: 2 }}
      >
        <ArrowBackIcon fontSize="small" />
        {t('offerings.backToSemesters')}
      </MuiLink>

      <Stack
        direction="row"
        sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 3, gap: 2 }}
      >
        <Typography variant="h4" component="h1">
          {semesterName
            ? t('offerings.titleFor', { semester: semesterName })
            : t('offerings.title')}
        </Typography>
        {canManage && (
          <Button variant="contained" startIcon={<AddIcon />} onClick={openCreate}>
            {t('offerings.new')}
          </Button>
        )}
      </Stack>

      {isPending && <CircularProgress aria-label={t('offerings.loading')} />}
      {isError && <Alert severity="error">{t('offerings.loadError')}</Alert>}
      {remove.isError && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {t('offerings.deleteError')}
        </Alert>
      )}

      {!isPending && !isError && offerings.length === 0 && (
        <Typography color="text.secondary">{t('offerings.empty')}</Typography>
      )}

      {offerings.length > 0 && (
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('offerings.course')}</TableCell>
                <TableCell>{t('offerings.instructor')}</TableCell>
                <TableCell>{t('offerings.capacity')}</TableCell>
                <TableCell>{t('offerings.meetings')}</TableCell>
                {canManage && <TableCell align="right">{t('offerings.actions')}</TableCell>}
              </TableRow>
            </TableHead>
            <TableBody>
              {offerings.map((offering) => (
                <OfferingRow
                  key={offering.id}
                  offering={offering}
                  canManage={canManage}
                  onEdit={openEdit}
                  onDelete={setConfirming}
                />
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      <OfferingFormDialog
        open={dialogOpen}
        onClose={() => setDialogOpen(false)}
        semesterId={semesterId}
        offering={editing}
      />

      <Dialog open={confirming !== null} onClose={() => setConfirming(null)}>
        <DialogTitle>{t('offerings.deleteTitle')}</DialogTitle>
        <DialogContent>
          <DialogContentText>
            {confirming &&
              t('offerings.deleteBody', {
                course: confirming.course?.code ?? '',
              })}
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirming(null)} disabled={remove.isPending}>
            {t('offerings.cancel')}
          </Button>
          <Button
            variant="contained"
            color="error"
            startIcon={<DeleteOutlineIcon />}
            onClick={confirmDelete}
            disabled={remove.isPending}
          >
            {remove.isPending ? t('offerings.deleting') : t('offerings.deleteAction')}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  )
}
