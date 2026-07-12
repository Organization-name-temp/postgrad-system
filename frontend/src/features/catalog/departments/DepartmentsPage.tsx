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
import { useListDepartments, useListFaculties } from '../../../shared/api/generated/endpoints'
import type { ListDepartments200DataItem } from '../../../shared/api/generated/endpoints'
import { usePermissions } from '../../../shared/access/usePermissions'
import { PERMISSION } from '../../../shared/access/permissions'
import { pickLocaleName } from '../localeName'
import { DepartmentRow } from './DepartmentRow'
import { DepartmentFormDialog } from './DepartmentFormDialog'

/**
 * The Departments admin screen. Everyone with catalog.view sees the list (code,
 * name, owning Faculty, status); only catalog.manage holders see the New / edit /
 * activate / deactivate controls — the API enforces the same split (ADR-0008).
 * Faculties are fetched alongside to resolve each Department's Faculty name and to
 * populate the create/edit Faculty picker.
 */
export function DepartmentsPage() {
  const { t, i18n } = useTranslation('catalog')
  const { hasPermission } = usePermissions()
  const canManage = hasPermission(PERMISSION.manageCatalog)

  const { data, isPending, isError } = useListDepartments()
  const departments = data?.data ?? []
  const { data: facultiesData } = useListFaculties()
  const faculties = facultiesData?.data ?? []

  const facultyName = (id?: number): string => {
    const faculty = faculties.find((item) => item.id === id)
    return faculty ? pickLocaleName(faculty, i18n.language, faculty.code ?? '') : ''
  }

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<ListDepartments200DataItem | undefined>(undefined)

  const openCreate = () => {
    setEditing(undefined)
    setDialogOpen(true)
  }
  const openEdit = (department: ListDepartments200DataItem) => {
    setEditing(department)
    setDialogOpen(true)
  }

  return (
    <>
      <Stack
        direction="row"
        sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 3, gap: 2 }}
      >
        <Typography variant="h4" component="h1">
          {t('departments.title')}
        </Typography>
        {canManage && (
          <Button variant="contained" startIcon={<AddIcon />} onClick={openCreate}>
            {t('departments.new')}
          </Button>
        )}
      </Stack>

      {isPending && <CircularProgress aria-label={t('departments.loading')} />}
      {isError && <Alert severity="error">{t('departments.loadError')}</Alert>}

      {!isPending && !isError && departments.length === 0 && (
        <Typography color="text.secondary">{t('departments.empty')}</Typography>
      )}

      {departments.length > 0 && (
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('departments.code')}</TableCell>
                <TableCell>{t('departments.name')}</TableCell>
                <TableCell>{t('departments.faculty')}</TableCell>
                <TableCell>{t('departments.statusHeader')}</TableCell>
                {canManage && <TableCell align="right">{t('departments.actions')}</TableCell>}
              </TableRow>
            </TableHead>
            <TableBody>
              {departments.map((department) => (
                <DepartmentRow
                  key={department.id}
                  department={department}
                  facultyName={facultyName(department.faculty_id)}
                  canManage={canManage}
                  onEdit={openEdit}
                />
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      <DepartmentFormDialog
        open={dialogOpen}
        onClose={() => setDialogOpen(false)}
        department={editing}
      />
    </>
  )
}
