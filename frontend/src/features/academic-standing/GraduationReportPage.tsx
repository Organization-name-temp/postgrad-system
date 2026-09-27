import { Alert, Button, CircularProgress, Stack, Typography } from '@mui/material'
import { Link as RouterLink } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAdvisoryGraduationReadinessCountsForTheAuthorizedFullSelection as useReadinessReport } from '../../shared/api/generated/endpoints'
import { Chart } from '../../shared/ui/Chart'
import { FilterBar } from '../../shared/ui/FilterBar'
import { ReportDocument } from '../../shared/ui/ReportDocument'
import {
  GRADUATION_BLOCKERS,
  READINESS_GROUPS,
  useGraduationReportCriteria,
} from './useGraduationReportCriteria'

export function GraduationReportPage() {
  const { t } = useTranslation('academicStanding')
  const collection = useGraduationReportCriteria()
  const report = useReadinessReport(collection.params)
  const summary = report.data?.data
  const selection = new URLSearchParams(collection.searchParams)
  selection.delete('page')
  selection.delete('per_page')
  const drill = (field: string, value: string) => {
    const query = new URLSearchParams(selection)
    query.set(field, value)
    return `/analytics/graduation-readiness/students?${query.toString()}`
  }
  return (
    <ReportDocument title={t('graduationReport.title')} criteria={collection.criteria}>
      <Alert severity="info">{t('graduationReport.advisory')}</Alert>
      {collection.values.lifecycle.includes('Terminated') && (
        <Alert severity="warning">{t('graduationReport.historical')}</Alert>
      )}
      <FilterBar
        fields={collection.fields}
        values={collection.values}
        onChange={collection.setCriteria}
      />
      {report.isPending && <CircularProgress aria-label={t('report.loading')} />}
      {report.isError && (
        <Alert
          severity="error"
          action={<Button onClick={() => void report.refetch()}>{t('report.retry')}</Button>}
        >
          {t('report.loadError')}
        </Alert>
      )}
      {summary && !report.isError && (
        <>
          <Typography>{t('report.population', { count: summary.total ?? 0 })}</Typography>
          {summary.total === 0 && <Alert severity="info">{t('report.empty')}</Alert>}
          <>
            <Chart
              layout="horizontal"
              label={t('graduationReport.groupsLabel')}
              categoryLabel={t('graduationReport.group')}
              valueLabel={t('report.students')}
              data={READINESS_GROUPS.map((value) => ({
                label: t(`graduationReport.groups.${value}`),
                value: summary.groups?.[value] ?? 0,
                href: (summary.groups?.[value] ?? 0) > 0 ? drill('readiness', value) : undefined,
              }))}
            />
          </>
          <>
            <Typography>{t('graduationReport.overlapping')}</Typography>
            <Chart
              layout="horizontal"
              label={t('graduationReport.blockersLabel')}
              categoryLabel={t('graduationReport.blocker')}
              valueLabel={t('report.students')}
              data={GRADUATION_BLOCKERS.map((value) => ({
                label: t(`graduationReport.blockers.${value}`),
                value: summary.blockers?.[value] ?? 0,
                href: (summary.blockers?.[value] ?? 0) > 0 ? drill('blocker', value) : undefined,
              }))}
            />
          </>
          <Stack direction="row" spacing={2} sx={{ '@media print': { display: 'none' } }}>
            <Button
              component={RouterLink}
              to={`/analytics/graduation-readiness/students?${selection.toString()}`}
            >
              {t('report.viewStudents')}
            </Button>
            <Button onClick={() => window.print()}>{t('report.print')}</Button>
          </Stack>
        </>
      )}
    </ReportDocument>
  )
}
