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
  useAdvisoryReadinessStudentListForTheAuthorizedReportSelection as useReadinessStudents,
  exportEveryStudentInTheAuthorizedAdvisoryReadinessSelection as exportReadinessStudents,
} from '../../shared/api/generated/endpoints'
import { usePermissions } from '../../shared/access/usePermissions'
import { downloadBlob } from '../../shared/downloadBlob'
import { pickLocaleName } from '../../shared/i18n/localeName'
import { DataToolbar } from '../../shared/ui/DataToolbar'
import { FilterBar } from '../../shared/ui/FilterBar'
import { ReportLoading } from '../../shared/ui/ReportLoading'
import { ReportDocument } from '../../shared/ui/ReportDocument'
import { StatusChip } from '../../shared/ui/StatusChip'
import { READINESS_GROUPS, useGraduationReportCriteria } from './useGraduationReportCriteria'

export function GraduationStudentListPage() {
  const { t, i18n } = useTranslation('academicStanding')
  const collection = useGraduationReportCriteria()
  const query = useReadinessStudents(collection.params)
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
        { value: '', label: t('report.sorts.studentId') },
        { value: 'remaining_ch', label: t('graduationReport.remainingAscending') },
        { value: '-remaining_ch', label: t('graduationReport.remainingDescending') },
        { value: '-cgpa', label: t('report.sorts.cgpaDescending') },
        { value: 'cgpa', label: t('report.sorts.cgpaAscending') },
        { value: 'name_en', label: t('report.sorts.nameEnglish') },
        { value: 'name_ar', label: t('report.sorts.nameArabic') },
      ],
    },
  ]
  return (
    <ReportDocument
      title={t('graduationReport.listTitle')}
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
      <Alert severity="info">{t('graduationReport.advisory')}</Alert>
      {collection.values.lifecycle.includes('Terminated') && (
        <Alert severity="warning">{t('graduationReport.historical')}</Alert>
      )}
      <FilterBar fields={fields} values={collection.values} onChange={collection.setCriteria} />
      {query.isPending && <ReportLoading label={t('report.studentsLoading')} layout="table" />}
      {query.isError && (
        <Alert
          severity="error"
          action={<Button onClick={() => void query.refetch()}>{t('report.retry')}</Button>}
        >
          {t('report.studentsError')}
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
              await exportReadinessStudents(collection.params),
              'graduation-readiness-students.xlsx',
            )
          }
        />
      )}
      {!query.isPending && !query.isError && rows.length === 0 && (
        <Alert severity="info">{t('report.empty')}</Alert>
      )}
      {!query.isError && rows.length > 0 && (
        <TableContainer>
          <Table
            size="small"
            aria-label={t('graduationReport.studentTable')}
            sx={{ minWidth: 900, '@media print': { minWidth: 0 } }}
          >
            <TableHead>
              <TableRow>
                {[
                  'name',
                  'universityId',
                  'program',
                  'degree',
                  'lifecycle',
                  'cgpa',
                  'readiness',
                  'remaining',
                  'approaching',
                  'blockers',
                ].map((column) => (
                  <TableCell key={column} scope="col">
                    {['readiness', 'remaining', 'approaching', 'blockers'].includes(column)
                      ? t(`graduationReport.columns.${column}`)
                      : t(`report.columns.${column}`)}
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
                const group = READINESS_GROUPS.find((value) => value === row.readiness_group)
                return (
                  <TableRow key={row.student_id}>
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
                      {row.readiness?.cgpa == null ? (
                        t('report.cgpaUnavailable')
                      ) : (
                        <bdi dir="ltr">{row.readiness.cgpa.toFixed(2)}</bdi>
                      )}
                    </TableCell>
                    <TableCell>
                      <StatusChip
                        variant="filled-soft"
                        tone={
                          group === 'ready'
                            ? 'success'
                            : group === 'not_ready'
                              ? 'warning'
                              : 'neutral'
                        }
                        label={group ? t(`graduationReport.groups.${group}`) : t('report.unknown')}
                      />
                    </TableCell>
                    <TableCell>
                      {row.readiness ? (
                        <bdi dir="ltr">{row.readiness.remaining_credit_hours}</bdi>
                      ) : (
                        t('report.unknown')
                      )}
                    </TableCell>
                    <TableCell>
                      {row.readiness
                        ? t(
                            row.readiness.approaching_graduation
                              ? 'graduationReport.yes'
                              : 'graduationReport.no',
                          )
                        : t('report.unknown')}
                    </TableCell>
                    <TableCell>
                      {row.readiness ? (
                        (row.readiness.blockers ?? []).length > 0 ? (
                          <ul>
                            {row.readiness.blockers?.map((blocker) => (
                              <li key={blocker}>
                                {t(`graduationReport.blockers.${blocker}`, {
                                  defaultValue: t('report.unknown'),
                                })}
                              </li>
                            ))}
                          </ul>
                        ) : (
                          t('graduationReport.noBlockers')
                        )
                      ) : (
                        t('graduationReport.curriculumUnavailable')
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
