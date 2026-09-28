import {
  Alert,
  Button,
  Link,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
} from '@mui/material'
import { Link as RouterLink } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  useScopedMinimalTerminationCaseList as useTerminationCases,
  exportTheCompleteScopedTerminationCaseSelection as exportTerminationCases,
} from '../../shared/api/generated/endpoints'
import { usePermissions } from '../../shared/access/usePermissions'
import { downloadBlob } from '../../shared/downloadBlob'
import { pickLocaleName } from '../../shared/i18n/localeName'
import { DataToolbar } from '../../shared/ui/DataToolbar'
import { FilterBar } from '../../shared/ui/FilterBar'
import { ReportLoading } from '../../shared/ui/ReportLoading'
import { ReportDocument } from '../../shared/ui/ReportDocument'
import { StatusChip, type StatusTone } from '../../shared/ui/StatusChip'
import { ROLE } from '../../shared/access/roles'
import { isoToInputValue } from '../../shared/datetime'
import {
  TERMINATION_REPORT_STATES,
  useTerminationReportCriteria,
} from './useTerminationReportCriteria'

const tones: Record<(typeof TERMINATION_REPORT_STATES)[number], StatusTone> = {
  AwaitingAdvisor: 'warning',
  AwaitingDepartmentHead: 'warning',
  AwaitingCouncil: 'warning',
  Dismissed: 'neutral',
  Terminated: 'error',
  TriggerCleared: 'success',
  EvidenceChanged: 'neutral',
}
const reviewRules = {
  AwaitingAdvisor: {
    url: '/termination/advisor',
    role: ROLE.advisor,
    permission: 'termination.recommend',
  },
  AwaitingDepartmentHead: {
    url: '/termination/department-head',
    role: ROLE.departmentHead,
    permission: 'termination.recommend',
  },
  AwaitingCouncil: {
    url: '/termination/council',
    role: ROLE.council,
    permission: 'termination.approve',
  },
}
const timestamps = [
  'created_at',
  'advisor_reviewed_at',
  'department_head_reviewed_at',
  'council_decided_at',
  'closed_at',
] as const

export function TerminationCaseListPage() {
  const { t, i18n } = useTranslation('academicStanding')
  const collection = useTerminationReportCriteria()
  const query = useTerminationCases(collection.params)
  const permissions = usePermissions()
  const canReadDetails = permissions.hasPermission('grades.view_student')
  const rows = query.data?.data ?? []
  const meta = query.data?.meta
  const fields = [
    ...collection.fields,
    {
      key: 'sort',
      label: t('report.sort'),
      options: [
        { value: '', label: t('terminationReport.caseId') },
        { value: '-created_at', label: t('terminationReport.createdDescending') },
        { value: 'created_at', label: t('terminationReport.createdAscending') },
      ],
    },
  ]
  return (
    <ReportDocument
      title={t('terminationReport.listTitle')}
      criteria={[
        ...collection.criteria,
        ...(meta
          ? [
              {
                label: t('report.page'),
                value: t('report.pageValue', { page: meta.current_page, last: meta.last_page }),
              },
            ]
          : []),
      ]}
    >
      <FilterBar fields={fields} values={collection.values} onChange={collection.setCriteria} />
      {query.isPending && (
        <ReportLoading label={t('terminationReport.listLoading')} layout="table" />
      )}
      {query.isError && (
        <Alert
          severity="error"
          action={<Button onClick={() => void query.refetch()}>{t('report.retry')}</Button>}
        >
          {t('terminationReport.listError')}
        </Alert>
      )}
      {meta && !query.isError && (
        <DataToolbar
          total={meta.total ?? 0}
          page={meta.current_page ?? collection.params.page}
          lastPage={meta.last_page ?? 1}
          onPageChange={collection.setPage}
          onExport={async () =>
            downloadBlob(
              await exportTerminationCases(collection.params),
              'termination-report-cases.xlsx',
            )
          }
        />
      )}
      {!query.isPending && !query.isError && rows.length === 0 && (
        <Alert severity="info">{t('terminationReport.empty')}</Alert>
      )}
      {!query.isError && rows.length > 0 && (
        <TableContainer>
          <Table
            size="small"
            aria-label={t('terminationReport.studentTable')}
            sx={{ minWidth: 900, '@media print': { minWidth: 0 } }}
          >
            <TableHead>
              <TableRow>
                {[
                  t('terminationReport.columns.case'),
                  t('report.columns.name'),
                  t('report.columns.universityId'),
                  t('report.columns.program'),
                  t('report.columns.degree'),
                  t('report.columns.lifecycle'),
                  ...['state', 'created', 'advisor', 'head', 'council', 'closed', 'review'].map(
                    (key) => t(`terminationReport.columns.${key}`),
                  ),
                ].map((label) => (
                  <TableCell key={label} scope="col">
                    {label}
                  </TableCell>
                ))}
              </TableRow>
            </TableHead>
            <TableBody>
              {rows.map((row) => {
                const name = pickLocaleName(
                  { name_en: row.name_en ?? undefined, name_ar: row.name_ar ?? undefined },
                  i18n.language,
                  t('report.nameUnavailable'),
                )
                const state = TERMINATION_REPORT_STATES.find((value) => value === row.state)
                const rule =
                  state === 'AwaitingAdvisor' ||
                  state === 'AwaitingDepartmentHead' ||
                  state === 'AwaitingCouncil'
                    ? reviewRules[state]
                    : undefined
                const canReview =
                  rule &&
                  row.lifecycle === 'Active' &&
                  row.review_url === rule.url &&
                  permissions.hasRole(rule.role) &&
                  permissions.hasPermission(rule.permission)
                return (
                  <TableRow key={row.case_id}>
                    <TableCell>
                      <bdi dir="ltr">{row.case_id}</bdi>
                    </TableCell>
                    <TableCell component="th" scope="row">
                      {canReadDetails && row.student_id !== undefined ? (
                        <Link component={RouterLink} to={`/grading/students/${row.student_id}`}>
                          {name}
                        </Link>
                      ) : (
                        name
                      )}
                    </TableCell>
                    <TableCell>
                      <bdi dir="ltr">{row.university_id}</bdi>
                    </TableCell>
                    <TableCell>
                      {row.program ? (
                        <>
                          {pickLocaleName(row.program, i18n.language)}{' '}
                          <bdi dir="ltr">{row.program.code}</bdi>
                        </>
                      ) : (
                        t('report.programUnavailable')
                      )}
                    </TableCell>
                    <TableCell>
                      {row.program?.degree_level === 'MSc'
                        ? t('report.msc')
                        : row.program?.degree_level === 'PhD'
                          ? t('report.phd')
                          : t('report.unknown')}
                    </TableCell>
                    <TableCell>
                      {t(`report.lifecycles.${row.lifecycle}`, {
                        defaultValue: t('report.unknown'),
                      })}
                    </TableCell>
                    <TableCell>
                      <StatusChip
                        variant="filled-soft"
                        tone={state ? tones[state] : 'neutral'}
                        label={state ? t(`terminationReport.states.${state}`) : t('report.unknown')}
                      />
                    </TableCell>
                    {timestamps.map((field) => {
                      const value = isoToInputValue(row[field] ?? undefined)
                      return (
                        <TableCell key={field}>
                          {value ? (
                            <bdi dir="ltr">{value.replace('T', ' ')}</bdi>
                          ) : (
                            t('report.unknown')
                          )}
                        </TableCell>
                      )
                    })}
                    <TableCell>
                      {canReview && rule ? (
                        <Link component={RouterLink} to={rule.url}>
                          {t('terminationReport.review')}
                        </Link>
                      ) : (
                        t('terminationReport.noReview')
                      )}
                    </TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        </TableContainer>
      )}
    </ReportDocument>
  )
}
