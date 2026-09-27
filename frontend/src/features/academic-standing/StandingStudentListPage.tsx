import {
  Alert,
  Button,
  CircularProgress,
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
  useCurrentStandingStudentListForTheSameAuthorizedReportSelection as useStandingStudents,
  exportEveryStudentInTheAuthorizedStandingReportSelection as exportStandingStudents,
} from '../../shared/api/generated/endpoints'
import { usePermissions } from '../../shared/access/usePermissions'
import { downloadBlob } from '../../shared/downloadBlob'
import { pickLocaleName } from '../../shared/i18n/localeName'
import { DataToolbar } from '../../shared/ui/DataToolbar'
import { FilterBar } from '../../shared/ui/FilterBar'
import { ReportDocument } from '../../shared/ui/ReportDocument'
import { StatusChip, type StatusTone } from '../../shared/ui/StatusChip'
import { ACADEMIC_STANDINGS, useAcademicReportCriteria } from './useAcademicReportCriteria'

const tones: Record<(typeof ACADEMIC_STANDINGS)[number], StatusTone> = {
  TerminationPending: 'error',
  NotYetAssessed: 'neutral',
  AcademicProbation: 'warning',
  AcademicWarning: 'warning',
  GoodStanding: 'success',
}

export function StandingStudentListPage() {
  const { t, i18n } = useTranslation('academicStanding')
  const collection = useAcademicReportCriteria()
  const query = useStandingStudents(collection.params)
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
        { value: '-standing', label: t('report.sorts.standingDescending') },
        { value: 'standing', label: t('report.sorts.standingAscending') },
        { value: '-cgpa', label: t('report.sorts.cgpaDescending') },
        { value: 'cgpa', label: t('report.sorts.cgpaAscending') },
        { value: 'name_en', label: t('report.sorts.nameEnglish') },
        { value: 'name_ar', label: t('report.sorts.nameArabic') },
      ],
    },
  ]
  return (
    <ReportDocument
      title={t('report.studentsTitle')}
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
      {query.isPending && <CircularProgress aria-label={t('report.studentsLoading')} />}
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
              await exportStandingStudents(collection.params),
              'academic-standing-students.xlsx',
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
            aria-label={t('report.studentTable')}
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
                  'standing',
                  'explanation',
                ].map((column) => (
                  <TableCell key={column} scope="col">
                    {t(`report.columns.${column}`)}
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
                const standing = ACADEMIC_STANDINGS.find((value) => value === row.standing)
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
                      {row.cgpa == null ? (
                        t('report.cgpaUnavailable')
                      ) : (
                        <bdi dir="ltr">{row.cgpa.toFixed(2)}</bdi>
                      )}
                    </TableCell>
                    <TableCell>
                      <StatusChip
                        variant="filled-soft"
                        tone={standing ? tones[standing] : 'neutral'}
                        label={standing ? t(`standing.values.${standing}`) : t('report.unknown')}
                      />
                    </TableCell>
                    <TableCell>
                      {t(`report.explanations.${row.explanation}`, {
                        defaultValue: t('report.unknown'),
                      })}
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
