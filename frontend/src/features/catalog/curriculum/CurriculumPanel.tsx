import { useState } from 'react'
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  CircularProgress,
  FormControl,
  IconButton,
  InputLabel,
  List,
  ListItem,
  ListItemText,
  MenuItem,
  Select,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import DeleteIcon from '@mui/icons-material/Delete'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListAProgramsCurriculumQueryKey,
  getListAProgramsVersionHistoryQueryKey,
  useAddACourseToAProgramsCurriculum,
  useAssignAPreparatoryRequirementRole,
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
import {
  isPreparatoryRequirementRole,
  PREPARATORY_REQUIREMENT_ROLES,
} from './preparatoryRequirementRoles'
import type { PreparatoryRequirementRole } from './preparatoryRequirementRoles'

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
  const missingRoles = (data?.meta?.missing_preparatory_requirement_roles ?? []).filter(
    isPreparatoryRequirementRole,
  )
  const add = useAddACourseToAProgramsCurriculum()
  const assignRole = useAssignAPreparatoryRequirementRole()
  const remove = useRemoveACurriculumEntryFromAProgram()
  const busy = add.isPending || assignRole.isPending || remove.isPending

  const refetch = () => {
    void queryClient.invalidateQueries({ queryKey: getListAProgramsCurriculumQueryKey(programId) })
    // A curriculum edit is a versioned Program change (ADR-0011) — refresh the
    // version-history panel on the same page too.
    void queryClient.invalidateQueries({
      queryKey: getListAProgramsVersionHistoryQueryKey(programId),
    })
  }

  const addCourse = (
    courseId: number,
    category: CourseCategory,
    preparatoryRequirementRole?: PreparatoryRequirementRole,
  ) =>
    add.mutate(
      {
        programId,
        data: {
          course_id: courseId,
          category,
          ...(preparatoryRequirementRole
            ? { preparatory_requirement_role: preparatoryRequirementRole }
            : {}),
        },
      },
      { onSuccess: refetch },
    )

  const changeRole = (
    curriculumEntryId: number,
    preparatoryRequirementRole: PreparatoryRequirementRole,
  ) =>
    assignRole.mutate(
      {
        programId,
        curriculumEntryId,
        data: { preparatory_requirement_role: preparatoryRequirementRole },
      },
      { onSuccess: refetch },
    )

  const removeEntry = (curriculumEntryId: number) =>
    remove.mutate({ programId, curriculumEntryId }, { onSuccess: refetch })

  return (
    <Box>
      <Typography variant="h5" component="h2" sx={{ mb: 1 }}>
        {t('curriculum.title')}
      </Typography>
      {isPending && <CircularProgress aria-label={t('curriculum.loading')} />}
      {isError && <Alert severity="error">{t('curriculum.loadError')}</Alert>}
      {(add.isError || assignRole.isError || remove.isError) && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {t('curriculum.saveError')}
        </Alert>
      )}

      {!isPending && !isError && data?.meta?.preparatory_requirements_complete === false && (
        <Alert severity="warning" sx={{ mb: 2 }}>
          {t('curriculum.missingRoles', {
            roles: missingRoles.map((role) => t(`curriculum.preparatoryRoles.${role}`)).join(', '),
          })}
        </Alert>
      )}

      {!isPending &&
        !isError &&
        COURSE_CATEGORIES.map((category) => (
          <CategorySection
            key={category}
            category={category}
            entries={entries.filter((entry) => entry.category === category)}
            canManage={canManage}
            busy={busy}
            onAdd={addCourse}
            onAssignRole={changeRole}
            onRemove={removeEntry}
          />
        ))}
    </Box>
  )
}

interface CategorySectionProps {
  category: CourseCategory
  entries: ListAProgramsCurriculum200DataItem[]
  canManage: boolean
  busy: boolean
  onAdd: (
    courseId: number,
    category: CourseCategory,
    preparatoryRequirementRole?: PreparatoryRequirementRole,
  ) => void
  onAssignRole: (
    curriculumEntryId: number,
    preparatoryRequirementRole: PreparatoryRequirementRole,
  ) => void
  onRemove: (curriculumEntryId: number) => void
}

/** One Category section: its placed Courses plus (for managers) an add picker. */
function CategorySection({
  category,
  entries,
  canManage,
  busy,
  onAdd,
  onAssignRole,
  onRemove,
}: CategorySectionProps) {
  const { t, i18n } = useTranslation('catalog')
  const [selected, setSelected] = useState<ListCourses200DataItem | null>(null)
  const [search, setSearch] = useState('')
  const [input, setInput] = useState('')
  const lookup = useListCourses(
    { q: search || undefined, per_page: 100 },
    { query: { enabled: canManage } },
  )
  const courses = lookup.data?.data ?? []
  const [selectedRole, setSelectedRole] = useState<PreparatoryRequirementRole | ''>('')

  const courseLabel = (course: ListCourses200DataItem) =>
    `${course.code ?? ''} — ${pickLocaleName(course, i18n.language, course.code ?? '')}`

  // Don't offer Courses already placed in this Category (the API rejects a
  // duplicate anyway; this keeps the picker honest).
  const placedIds = new Set(entries.map((entry) => entry.course?.id))
  const available = courses.filter((course) => !placedIds.has(course.id))

  const handleAdd = () => {
    if (selected?.id !== undefined) {
      onAdd(
        selected.id,
        category,
        category === 'Preparatory' && selectedRole ? selectedRole : undefined,
      )
      setSelected(null)
      setInput('')
      setSearch('')
      setSelectedRole('')
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
                  <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                    {category === 'Preparatory' && (
                      <FormControl size="small" sx={{ minWidth: 210 }}>
                        <InputLabel id={`role-${entry.id}-label`}>
                          {t('curriculum.roleFor', { code: entry.course?.code ?? '' })}
                        </InputLabel>
                        <Select
                          labelId={`role-${entry.id}-label`}
                          value={entry.preparatory_requirement_role ?? ''}
                          label={t('curriculum.roleFor', { code: entry.course?.code ?? '' })}
                          onChange={(event) => {
                            const role = event.target.value
                            if (isPreparatoryRequirementRole(role)) {
                              onAssignRole(entry.id!, role)
                            }
                          }}
                          disabled={busy}
                        >
                          {PREPARATORY_REQUIREMENT_ROLES.map((role) => (
                            <MenuItem key={role} value={role}>
                              {t(`curriculum.preparatoryRoles.${role}`)}
                            </MenuItem>
                          ))}
                        </Select>
                      </FormControl>
                    )}
                    <IconButton
                      edge="end"
                      size="small"
                      onClick={() => onRemove(entry.id!)}
                      disabled={busy}
                      aria-label={t('curriculum.remove', { code: entry.course?.code ?? '' })}
                    >
                      <DeleteIcon fontSize="small" />
                    </IconButton>
                  </Stack>
                ) : undefined
              }
            >
              <ListItemText
                primary={entry.course ? courseLabel(entry.course) : ''}
                secondary={
                  category === 'Preparatory' &&
                  isPreparatoryRequirementRole(entry.preparatory_requirement_role)
                    ? t('curriculum.levelAndRole', {
                        level: entry.course?.level ?? '',
                        role: t(
                          `curriculum.preparatoryRoles.${entry.preparatory_requirement_role}`,
                        ),
                      })
                    : t('curriculum.level', { level: entry.course?.level ?? '' })
                }
              />
            </ListItem>
          ))}
        </List>
      )}

      {canManage && (
        <Stack direction="row" spacing={1} sx={{ mt: 1, alignItems: 'center' }}>
          <Autocomplete
            value={selected}
            inputValue={selected ? courseLabel(selected) : input}
            onChange={(_event, next) => setSelected(next)}
            onInputChange={(_event, text, reason) => {
              if (reason === 'input' || reason === 'clear') {
                setInput(text)
                setSearch(text)
                setSelected(null)
              }
            }}
            options={available}
            filterOptions={(rows) => rows}
            getOptionKey={(course) => course.id ?? ''}
            loading={lookup.isFetching}
            loadingText={t('references.loading')}
            noOptionsText={lookup.isError ? t('references.error') : t('references.noMatch')}
            clearText={t('references.clear')}
            openText={t('references.open')}
            closeText={t('references.close')}
            getOptionLabel={courseLabel}
            isOptionEqualToValue={(option, value) => option.id === value.id}
            sx={{ minWidth: 280 }}
            renderInput={(params) => (
              <TextField
                {...params}
                label={t('curriculum.addCourse')}
                size="small"
                error={lookup.isError}
                helperText={lookup.isError ? t('references.error') : undefined}
              />
            )}
          />
          {category === 'Preparatory' && (
            <FormControl size="small" sx={{ minWidth: 240 }}>
              <InputLabel id="new-preparatory-role-label">
                {t('curriculum.preparatoryRequirementRole')}
              </InputLabel>
              <Select
                labelId="new-preparatory-role-label"
                value={selectedRole}
                label={t('curriculum.preparatoryRequirementRole')}
                onChange={(event) => {
                  const role = event.target.value
                  if (isPreparatoryRequirementRole(role)) {
                    setSelectedRole(role)
                  }
                }}
              >
                {PREPARATORY_REQUIREMENT_ROLES.map((role) => (
                  <MenuItem key={role} value={role}>
                    {t(`curriculum.preparatoryRoles.${role}`)}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          )}
          <Button
            variant="outlined"
            onClick={handleAdd}
            disabled={
              busy || selected === null || (category === 'Preparatory' && selectedRole === '')
            }
          >
            {t('curriculum.add')}
          </Button>
        </Stack>
      )}
    </Box>
  )
}
