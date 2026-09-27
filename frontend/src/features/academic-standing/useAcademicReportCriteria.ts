import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import {
  useListDepartments,
  useListFaculties,
  useListPrograms,
} from '../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../shared/i18n/localeName'
import type { FilterField, FilterOption } from '../../shared/ui/FilterBar'
import { useCollectionQuery } from '../../shared/ui/useCollectionQuery'

export const ACADEMIC_STANDINGS = [
  'TerminationPending',
  'NotYetAssessed',
  'AcademicProbation',
  'AcademicWarning',
  'GoodStanding',
] as const

/** Shared only by the AcademicStanding owner's Report and ordinary Student List. */
export function useAcademicReportCriteria(includeStanding = true) {
  const { t, i18n } = useTranslation('academicStanding')
  const collection = useCollectionQuery()
  const [facultySearch, setFacultySearch] = useState<string | null>(null)
  const [departmentSearch, setDepartmentSearch] = useState<string | null>(null)
  const [programSearch, setProgramSearch] = useState<string | null>(null)
  const faculty = collection.searchParams.get('faculty_id')
  const department = collection.searchParams.get('department_id')
  const program = collection.searchParams.get('program_id')
  const faculties = useListFaculties({ q: facultySearch ?? undefined, per_page: 100 })
  const departments = useListDepartments({ q: departmentSearch ?? undefined, per_page: 100 })
  const programs = useListPrograms({ q: programSearch ?? undefined, per_page: 100 })
  const selectedFaculties = useListFaculties(
    { id: faculty ?? undefined, per_page: 100 },
    { query: { enabled: Boolean(faculty) } },
  )
  const selectedDepartments = useListDepartments(
    { id: department ?? undefined, per_page: 100 },
    { query: { enabled: Boolean(department) } },
  )
  const selectedPrograms = useListPrograms(
    { id: program ?? undefined, per_page: 100 },
    { query: { enabled: Boolean(program) } },
  )
  type ReferenceRow = { id?: number; name_en?: string; name_ar?: string }
  const option = (row: ReferenceRow): FilterOption => ({
    value: String(row.id),
    label: pickLocaleName(row, i18n.language, String(row.id)),
  })
  const selected = (
    ids: string | null,
    rows: ReferenceRow[] | undefined,
  ): FilterOption | undefined =>
    ids
      ? {
          value: ids,
          label: ids
            .split(',')
            .map((id) => {
              const row = rows?.find((item) => String(item.id) === id.trim())
              return row ? option(row).label : t('report.referenceUnavailable', { id: id.trim() })
            })
            .join(', '),
        }
      : undefined
  const facultyChoice = selected(faculty, selectedFaculties.data?.data)
  const departmentChoice = selected(department, selectedDepartments.data?.data)
  const programChoice = selected(program, selectedPrograms.data?.data)
  const referenceCopy = {
    noOptionsText: t('report.noMatches'),
    loadingText: t('report.referenceLoading'),
    clearText: t('report.clear'),
    openText: t('report.open'),
    closeText: t('report.close'),
  }
  const fields: FilterField[] = [
    { key: 'q', label: t('report.search') },
    {
      key: 'faculty_id',
      label: t('report.faculty'),
      reference: {
        ...referenceCopy,
        options: (faculties.data?.data ?? []).map(option),
        selected: facultyChoice,
        query: facultySearch,
        onSearch: setFacultySearch,
        loading: faculties.isFetching,
      },
    },
    {
      key: 'department_id',
      label: t('report.department'),
      reference: {
        ...referenceCopy,
        options: (departments.data?.data ?? []).map(option),
        selected: departmentChoice,
        query: departmentSearch,
        onSearch: setDepartmentSearch,
        loading: departments.isFetching,
      },
    },
    {
      key: 'program_id',
      label: t('report.program'),
      reference: {
        ...referenceCopy,
        options: (programs.data?.data ?? []).map(option),
        selected: programChoice,
        query: programSearch,
        onSearch: setProgramSearch,
        loading: programs.isFetching,
      },
    },
    {
      key: 'degree_level',
      label: t('report.degree'),
      options: [
        { value: '', label: t('report.all') },
        { value: 'MSc', label: t('report.msc') },
        { value: 'PhD', label: t('report.phd') },
      ],
    },
    {
      key: 'lifecycle',
      label: t('report.lifecycle'),
      options: [
        { value: 'Active', label: t('report.lifecycles.Active') },
        { value: 'Terminated', label: t('report.lifecycles.Terminated') },
        { value: 'Active,Terminated', label: t('report.all') },
      ],
    },
    {
      key: 'standing',
      label: t('report.standing'),
      options: [
        { value: '', label: t('report.all') },
        ...ACADEMIC_STANDINGS.map((value) => ({ value, label: t(`standing.values.${value}`) })),
      ],
    },
  ]
  const labelFor = (key: string, value: string | null): string => {
    const options = fields.find((field) => field.key === key)?.options
    return value
      ? value
          .split(',')
          .map(
            (token) => options?.find((item) => item.value === token.trim())?.label ?? token.trim(),
          )
          .join(', ')
      : t('report.all')
  }
  const criteria = [
    { label: t('report.search'), value: collection.searchParams.get('q') ?? t('report.all') },
    { label: t('report.faculty'), value: facultyChoice?.label ?? t('report.all') },
    { label: t('report.department'), value: departmentChoice?.label ?? t('report.all') },
    { label: t('report.program'), value: programChoice?.label ?? t('report.all') },
    {
      label: t('report.degree'),
      value: labelFor('degree_level', collection.searchParams.get('degree_level')),
    },
    {
      label: t('report.lifecycle'),
      value: labelFor('lifecycle', collection.searchParams.get('lifecycle') ?? 'Active'),
    },
    {
      label: t('report.standing'),
      value: labelFor('standing', collection.searchParams.get('standing')),
    },
  ]
  return {
    ...collection,
    fields: includeStanding ? fields : fields.filter((field) => field.key !== 'standing'),
    criteria: includeStanding ? criteria : criteria.slice(0, -1),
    values: {
      ...collection.params,
      lifecycle: collection.searchParams.get('lifecycle') ?? 'Active',
    },
  }
}
