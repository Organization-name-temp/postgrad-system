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
import { useListCourses, useListDepartments } from '../../../shared/api/generated/endpoints'
import { usePermissions } from '../../../shared/access/usePermissions'
import { PERMISSION } from '../../../shared/access/permissions'
import { pickLocaleName } from '../localeName'
import { CourseRow } from './CourseRow'
import { CourseFormDialog } from './CourseFormDialog'

/**
 * The Courses admin screen. Everyone with catalog.view sees the list (code, name,
 * owning Department or "university-wide", level, the L/T/Lab · Total credit split,
 * status); only catalog.manage holders see the New / edit / activate / deactivate
 * controls — the API enforces the same split (ADR-0008). Departments are fetched
 * alongside to resolve each Course's owning Department name and to populate the
 * create/edit picker.
 */
export function CoursesPage() {
  const { t, i18n } = useTranslation('catalog')
  const { hasPermission } = usePermissions()
  const canManage = hasPermission(PERMISSION.manageCatalog)

  const { data, isPending, isError } = useListCourses()
  const courses = data?.data ?? []
  const { data: departmentsData } = useListDepartments()
  const departments = departmentsData?.data ?? []

  const departmentName = (id?: number): string | undefined => {
    const department = departments.find((item) => item.id === id)
    return department ? pickLocaleName(department, i18n.language, department.code ?? '') : undefined
  }

  // The list creates Courses; editing a Course's attributes and viewing its version
  // history happen on its own edit page (each row's edit action links there).
  const [dialogOpen, setDialogOpen] = useState(false)

  return (
    <>
      <Stack
        direction="row"
        sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 3, gap: 2 }}
      >
        <Typography variant="h4" component="h1">
          {t('courses.title')}
        </Typography>
        {canManage && (
          <Button variant="contained" startIcon={<AddIcon />} onClick={() => setDialogOpen(true)}>
            {t('courses.new')}
          </Button>
        )}
      </Stack>

      {isPending && <CircularProgress aria-label={t('courses.loading')} />}
      {isError && <Alert severity="error">{t('courses.loadError')}</Alert>}

      {!isPending && !isError && courses.length === 0 && (
        <Typography color="text.secondary">{t('courses.empty')}</Typography>
      )}

      {courses.length > 0 && (
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('courses.code')}</TableCell>
                <TableCell>{t('courses.name')}</TableCell>
                <TableCell>{t('courses.department')}</TableCell>
                <TableCell>{t('courses.level')}</TableCell>
                <TableCell>{t('courses.credits')}</TableCell>
                <TableCell>{t('courses.statusHeader')}</TableCell>
                {canManage && <TableCell align="right">{t('courses.actions')}</TableCell>}
              </TableRow>
            </TableHead>
            <TableBody>
              {courses.map((course) => (
                <CourseRow
                  key={course.id}
                  course={course}
                  departmentName={departmentName(course.department_id)}
                  canManage={canManage}
                />
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      <CourseFormDialog open={dialogOpen} onClose={() => setDialogOpen(false)} />
    </>
  )
}
