import { useState } from 'react'
import { Link as RouterLink, useParams } from 'react-router-dom'
import {
  Alert,
  Box,
  Breadcrumbs,
  Button,
  CircularProgress,
  Divider,
  Link,
  List,
  ListItem,
  ListItemText,
  Stack,
  Typography,
} from '@mui/material'
import EditIcon from '@mui/icons-material/Edit'
import { useTranslation } from 'react-i18next'
import {
  useListACoursesVersionHistory,
  useListDepartments,
  useShowACourse,
} from '../../../shared/api/generated/endpoints'
import { usePermissions } from '../../../shared/access/usePermissions'
import { PERMISSION } from '../../../shared/access/permissions'
import { VersionHistoryPanel } from '../versions/VersionHistoryPanel'
import { pickLocaleName } from '../localeName'
import { CourseFormDialog } from './CourseFormDialog'

/**
 * The Course detail page (routed at /catalog/courses/:courseId) — reached both from
 * the admin Courses list and by clicking a Course in the browse tree. It shows a
 * single Course's full detail: its header (code, level, credit split, owning
 * Department or "university-wide"), the "Appears in" placements (which Programs and
 * Categories it belongs to — FR-PCM-002), and the read-only version-history panel.
 * Everyone with catalog.view can read it; only catalog.manage sees the "Edit
 * details" control (the API enforces both — ADR-0008/0011).
 */
export function CourseEditPage() {
  const { courseId: courseIdParam } = useParams()
  const courseId = Number(courseIdParam)
  const { t, i18n } = useTranslation('catalog')
  const { hasPermission } = usePermissions()
  const canManage = hasPermission(PERMISSION.manageCatalog)

  const { data, isPending, isError } = useShowACourse(courseId)
  const course = data?.data

  // History is a catalog.view read (story 35), so everyone who can reach the page
  // can see it — no manage gate.
  const versions = useListACoursesVersionHistory(courseId)
  // Departments resolve the owning Department's name for the header (a Course
  // carries only its department_id); a university-wide Course has none.
  const { data: departmentsData } = useListDepartments()
  const departments = departmentsData?.data ?? []

  const [detailsOpen, setDetailsOpen] = useState(false)

  if (isPending) {
    return <CircularProgress aria-label={t('courses.loading')} />
  }
  if (isError || !course) {
    return <Alert severity="error">{t('courses.loadError')}</Alert>
  }

  // Both language names (story 7 / ticket item 4): the active locale as the heading,
  // the other language beneath it.
  const name = pickLocaleName(course, i18n.language, course.code ?? '')
  const altName = i18n.language === 'ar' ? course.name_en : course.name_ar
  const department = departments.find((item) => item.id === course.department_id)
  const owner =
    course.department_id != null
      ? department && pickLocaleName(department, i18n.language, department.code ?? '')
      : t('courses.universityWide')
  const credits = t('courses.creditSplit', {
    lecture: course.lecture_ch ?? 0,
    tutorial: course.tutorial_ch ?? 0,
    lab: course.lab_ch ?? 0,
    total: course.total_ch ?? 0,
  })

  return (
    <>
      <Breadcrumbs sx={{ mb: 2 }}>
        <Link component={RouterLink} to="/catalog/courses" underline="hover" color="inherit">
          {t('courses.title')}
        </Link>
        <Typography color="text.primary">{course.code}</Typography>
      </Breadcrumbs>

      <Stack
        direction="row"
        sx={{ alignItems: 'flex-start', justifyContent: 'space-between', gap: 2, mb: 1 }}
      >
        <Box>
          <Typography variant="h4" component="h1">
            {name}
          </Typography>
          {altName && altName !== name && (
            <Typography variant="subtitle1" color="text.secondary">
              {altName}
            </Typography>
          )}
          <Typography color="text.secondary">
            {course.code} · {t('courses.level')} {course.level} · {credits}
            {owner ? ` · ${owner}` : ''}
          </Typography>
        </Box>
        {canManage && (
          <Button variant="outlined" startIcon={<EditIcon />} onClick={() => setDetailsOpen(true)}>
            {t('courses.editDetails')}
          </Button>
        )}
      </Stack>

      <Divider sx={{ my: 3 }} />
      <Typography variant="h5" component="h2" sx={{ mb: 1 }}>
        {t('courses.appearsIn')}
      </Typography>
      {course.placements && course.placements.length > 0 ? (
        <List dense sx={{ mb: 1 }}>
          {course.placements.map((placement) => (
            <ListItem key={`${placement.program?.id}-${placement.category}`} disableGutters>
              <ListItemText
                primary={pickLocaleName(
                  placement.program ?? {},
                  i18n.language,
                  placement.program?.code ?? '',
                )}
                secondary={t(
                  `curriculum.categories.${placement.category}`,
                  placement.category ?? '',
                )}
              />
            </ListItem>
          ))}
        </List>
      ) : (
        <Typography color="text.secondary" variant="body2">
          {t('courses.appearsInEmpty')}
        </Typography>
      )}

      <Divider sx={{ my: 3 }} />
      <VersionHistoryPanel
        versions={versions.data?.data ?? []}
        isPending={versions.isPending}
        isError={versions.isError}
      />

      <CourseFormDialog open={detailsOpen} onClose={() => setDetailsOpen(false)} course={course} />
    </>
  )
}
