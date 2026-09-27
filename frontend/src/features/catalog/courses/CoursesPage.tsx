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
  exportCourses,
  useListCourses,
  useListDepartments,
} from '../../../shared/api/generated/endpoints'
import { usePermissions } from '../../../shared/access/usePermissions'
import { PERMISSION } from '../../../shared/access/permissions'
import { pickLocaleName } from '../localeName'
import { CourseRow } from './CourseRow'
import { CourseFormDialog } from './CourseFormDialog'
import { FilterBar } from '../../../shared/ui/FilterBar'
import { DataToolbar } from '../../../shared/ui/DataToolbar'
import { useCollectionQuery } from '../../../shared/ui/useCollectionQuery'
import { downloadBlob } from '../../../shared/downloadBlob'

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

  const collection = useCollectionQuery()
  const params = {
    ...collection.params,
    total_ch_min: collection.searchParams.has('total_ch_min')
      ? Number(collection.searchParams.get('total_ch_min'))
      : undefined,
    total_ch_max: collection.searchParams.has('total_ch_max')
      ? Number(collection.searchParams.get('total_ch_max'))
      : undefined,
  }
  const { data, isPending, isError } = useListCourses(params)
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

      <FilterBar
        values={collection.params}
        onChange={collection.setCriteria}
        fields={[
          { key: 'q', label: t('filters.search') },
          {
            key: 'department_id',
            label: t('courses.department'),
            options: [
              { value: '', label: t('filters.all') },
              ...departments.map((department) => ({
                value: String(department.id),
                label: pickLocaleName(department, i18n.language, department.code ?? ''),
              })),
            ],
          },
          {
            key: 'level',
            label: t('courses.level'),
            options: [
              { value: '', label: t('filters.all') },
              ...[400, 500, 600, 700].map((level) => ({
                value: String(level),
                label: String(level),
              })),
            ],
          },
          { key: 'total_ch_min', label: t('filters.minCredits'), type: 'number' },
          { key: 'total_ch_max', label: t('filters.maxCredits'), type: 'number' },
          {
            key: 'research_type',
            label: t('courses.researchType'),
            options: [
              { value: '', label: t('filters.all') },
              ...['PBL', 'Seminar'].map((type) => ({
                value: type,
                label: t(`courses.researchTypes.${type}`),
              })),
            ],
          },
          {
            key: 'is_active',
            label: t('courses.statusHeader'),
            options: [
              { value: '', label: t('filters.all') },
              { value: 'true', label: t('status.active') },
              { value: 'false', label: t('status.inactive') },
            ],
          },
          {
            key: 'sort',
            label: t('filters.sort'),
            options: [
              { value: '', label: t('courses.code') },
              { value: 'name_en', label: t('filters.englishName') },
              { value: 'name_ar', label: t('filters.arabicName') },
              { value: '-level,code', label: t('filters.levelDescending') },
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
          onExport={async () => downloadBlob(await exportCourses(params), 'courses.xlsx')}
        />
      )}

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
