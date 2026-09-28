import { useTranslation } from 'react-i18next'
import type { FilterField } from '../../shared/ui/FilterBar'
import { useAcademicReportCriteria } from './useAcademicReportCriteria'

export const TERMINATION_REPORT_STATES = [
  'AwaitingAdvisor',
  'AwaitingDepartmentHead',
  'AwaitingCouncil',
  'Dismissed',
  'Terminated',
  'TriggerCleared',
  'EvidenceChanged',
] as const

export function useTerminationReportCriteria() {
  const { t } = useTranslation('academicStanding')
  const base = useAcademicReportCriteria(false, 'Active,Terminated')
  const extra: FilterField[] = [
    {
      key: 'state',
      label: t('terminationReport.state'),
      options: [
        { value: '', label: t('report.all') },
        ...TERMINATION_REPORT_STATES.map((value) => ({
          value,
          label: t(`terminationReport.states.${value}`),
        })),
      ],
    },
    {
      key: 'history',
      label: t('terminationReport.history'),
      options: [
        { value: 'default', label: t('terminationReport.defaultHistory') },
        { value: 'include', label: t('terminationReport.allHistory') },
      ],
    },
    { key: 'created_from', type: 'date', label: t('terminationReport.createdFrom') },
    { key: 'created_to', type: 'date', label: t('terminationReport.createdTo') },
  ]
  const values = { ...base.values, history: base.searchParams.get('history') ?? 'default' }
  return {
    ...base,
    fields: [...base.fields, ...extra],
    values,
    criteria: [
      ...base.criteria,
      ...extra.map((field) => {
        const value = field.key === 'history' ? values.history : base.searchParams.get(field.key)
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
