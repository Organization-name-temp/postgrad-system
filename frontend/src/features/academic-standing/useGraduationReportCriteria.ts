import { useTranslation } from 'react-i18next'
import type { FilterField } from '../../shared/ui/FilterBar'
import { useAcademicReportCriteria } from './useAcademicReportCriteria'

export const READINESS_GROUPS = ['ready', 'not_ready', 'unavailable'] as const
export const GRADUATION_BLOCKERS = [
  'RemainingRequirements',
  'CgpaUnavailable',
  'CgpaBelowMinimum',
  'TerminationPending',
] as const

export function useGraduationReportCriteria() {
  const { t } = useTranslation('academicStanding')
  const base = useAcademicReportCriteria(false)
  const all = { value: '', label: t('report.all') }
  const extra: FilterField[] = [
    {
      key: 'readiness',
      label: t('graduationReport.group'),
      options: [
        all,
        ...READINESS_GROUPS.map((value) => ({
          value,
          label: t(`graduationReport.groups.${value}`),
        })),
      ],
    },
    {
      key: 'blocker',
      label: t('graduationReport.blocker'),
      options: [
        all,
        ...GRADUATION_BLOCKERS.map((value) => ({
          value,
          label: t(`graduationReport.blockers.${value}`),
        })),
      ],
    },
    {
      key: 'blocker_all',
      label: t('graduationReport.blockerAll'),
      options: [
        all,
        ...GRADUATION_BLOCKERS.map((value) => ({
          value,
          label: t(`graduationReport.blockers.${value}`),
        })),
      ],
    },
    {
      key: 'proximity',
      label: t('graduationReport.proximity'),
      options: [all, { value: 'remaining_1_5', label: t('graduationReport.proximityChoice') }],
    },
    { key: 'remaining_ch_min', label: t('graduationReport.remainingMin') },
    { key: 'remaining_ch_max', label: t('graduationReport.remainingMax') },
    { key: 'cgpa_min', label: t('graduationReport.cgpaMin') },
    { key: 'cgpa_max', label: t('graduationReport.cgpaMax') },
    {
      key: 'cgpa_availability',
      label: t('graduationReport.cgpaAvailability'),
      options: [
        all,
        { value: 'available', label: t('graduationReport.available') },
        { value: 'unavailable', label: t('graduationReport.unavailable') },
      ],
    },
  ]
  const number = (key: string) =>
    base.searchParams.has(key) ? Number(base.searchParams.get(key)) : undefined
  return {
    ...base,
    fields: [...base.fields, ...extra],
    params: {
      ...base.params,
      remaining_ch_min: number('remaining_ch_min'),
      remaining_ch_max: number('remaining_ch_max'),
      cgpa_min: number('cgpa_min'),
      cgpa_max: number('cgpa_max'),
    },
    criteria: [
      ...base.criteria,
      ...extra.map((field) => {
        const value = base.searchParams.get(field.key)
        return {
          label: field.label,
          value: value
            ? value
                .split(',')
                .map(
                  (item) => field.options?.find((option) => option.value === item)?.label ?? item,
                )
                .join(', ')
            : t('report.all'),
        }
      }),
    ],
  }
}
