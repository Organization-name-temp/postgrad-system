import { useState } from 'react'
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  CircularProgress,
  IconButton,
  List,
  ListItem,
  ListItemText,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import DeleteIcon from '@mui/icons-material/Delete'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListAProgramsCurriculumQueryKey,
  useAddACourseToAProgramsCurriculum,
  useListAProgramsCurriculum,
  useListCourses,
  useRemoveACurriculumEntryFromAProgram,
} from '../../../shared/api/generated/endpoints'
import type {
  ListAProgramsCurriculum200DataItem,
  ListCourses200DataItem,
} from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../localeName'
import { COURSE_CATEGORIES } from './courseCategories'
import type { CourseCategory } from './courseCategories'

interface CurriculumPanelProps {
  programId: number
  canManage: boolean
}

/**
 * The Program edit page's Curriculum editor: three Category sections
 * (Preparatory / Program Core / Research Elective), each listing the Courses placed
 * there and — for a catalog.manage holder — an autocomplete Course-picker to add
 * one and a remove control per entry. Category lives on the Curriculum Entry, so
 * the same Course can sit in this Program's section and another Program's too
 * (ADR-0010). The API is the sole authority; mutations invalidate the curriculum
 * query so the sections re-render.
 */
export function CurriculumPanel({ programId, canManage }: CurriculumPanelProps) {
  const { t } = useTranslation('catalog')
  const queryClient = useQueryClient()

  const { data, isPending, isError } = useListAProgramsCurriculum(programId)
  const entries = data?.data ?? []
  const { data: coursesData } = useListCourses()
  const courses = coursesData?.data ?? []

  const add = useAddACourseToAProgramsCurriculum()
  const remove = useRemoveACurriculumEntryFromAProgram()
  const busy = add.isPending || remove.isPending

  const refetch = () =>
    void queryClient.invalidateQueries({ queryKey: getListAProgramsCurriculumQueryKey(programId) })

  const addCourse = (courseId: number, category: CourseCategory) =>
    add.mutate({ programId, data: { course_id: courseId, category } }, { onSuccess: refetch })

  const removeEntry = (curriculumEntryId: number) =>
    remove.mutate({ programId, curriculumEntryId }, { onSuccess: refetch })

  return (
    <Box>
      <Typography variant="h5" component="h2" sx={{ mb: 1 }}>
        {t('curriculum.title')}
      </Typography>
      {isPending && <CircularProgress aria-label={t('curriculum.loading')} />}
      {isError && <Alert severity="error">{t('curriculum.loadError')}</Alert>}
      {(add.isError || remove.isError) && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {t('curriculum.saveError')}
        </Alert>
      )}

      {!isPending &&
        !isError &&
        COURSE_CATEGORIES.map((category) => (
          <CategorySection
            key={category}
            category={category}
            entries={entries.filter((entry) => entry.category === category)}
            courses={courses}
            canManage={canManage}
            busy={busy}
            onAdd={addCourse}
            onRemove={removeEntry}
          />
        ))}
    </Box>
  )
}

interface CategorySectionProps {
  category: CourseCategory
  entries: ListAProgramsCurriculum200DataItem[]
  courses: ListCourses200DataItem[]
  canManage: boolean
  busy: boolean
  onAdd: (courseId: number, category: CourseCategory) => void
  onRemove: (curriculumEntryId: number) => void
}

/** One Category section: its placed Courses plus (for managers) an add picker. */
function CategorySection({
  category,
  entries,
  courses,
  canManage,
  busy,
  onAdd,
  onRemove,
}: CategorySectionProps) {
  const { t, i18n } = useTranslation('catalog')
  const [selected, setSelected] = useState<ListCourses200DataItem | null>(null)

  const courseLabel = (course: ListCourses200DataItem) =>
    `${course.code ?? ''} — ${pickLocaleName(course, i18n.language, course.code ?? '')}`

  // Don't offer Courses already placed in this Category (the API rejects a
  // duplicate anyway; this keeps the picker honest).
  const placedIds = new Set(entries.map((entry) => entry.course?.id))
  const available = courses.filter((course) => !placedIds.has(course.id))

  const handleAdd = () => {
    if (selected?.id !== undefined) {
      onAdd(selected.id, category)
      setSelected(null)
    }
  }

  return (
    <Box component="section" data-testid={`curriculum-section-${category}`} sx={{ mb: 3 }}>
      <Typography variant="subtitle1" component="h3" sx={{ fontWeight: 600 }}>
        {t(`curriculum.categories.${category}`)}
      </Typography>

      {entries.length === 0 ? (
        <Typography color="text.secondary" variant="body2">
          {t('curriculum.emptyCategory')}
        </Typography>
      ) : (
        <List dense>
          {entries.map((entry) => (
            <ListItem
              key={entry.id}
              secondaryAction={
                canManage && entry.id !== undefined ? (
                  <IconButton
                    edge="end"
                    size="small"
                    onClick={() => onRemove(entry.id!)}
                    disabled={busy}
                    aria-label={t('curriculum.remove', { code: entry.course?.code ?? '' })}
                  >
                    <DeleteIcon fontSize="small" />
                  </IconButton>
                ) : undefined
              }
            >
              <ListItemText
                primary={entry.course ? courseLabel(entry.course) : ''}
                secondary={t('curriculum.level', { level: entry.course?.level ?? '' })}
              />
            </ListItem>
          ))}
        </List>
      )}

      {canManage && (
        <Stack direction="row" spacing={1} sx={{ mt: 1, alignItems: 'center' }}>
          <Autocomplete
            value={selected}
            onChange={(_event, next) => setSelected(next)}
            options={available}
            getOptionLabel={courseLabel}
            isOptionEqualToValue={(option, value) => option.id === value.id}
            sx={{ minWidth: 280 }}
            renderInput={(params) => (
              <TextField {...params} label={t('curriculum.addCourse')} size="small" />
            )}
          />
          <Button variant="outlined" onClick={handleAdd} disabled={busy || selected === null}>
            {t('curriculum.add')}
          </Button>
        </Stack>
      )}
    </Box>
  )
}
