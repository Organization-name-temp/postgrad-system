import { Alert, Button, CircularProgress, Stack, Typography } from '@mui/material'
import { Link as RouterLink } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useCurrentAcademicStandingCountsForTheAuthorizedFullSelection as useStandingReport } from '../../shared/api/generated/endpoints'
import { Chart } from '../../shared/ui/Chart'
import { FilterBar } from '../../shared/ui/FilterBar'
import { ReportDocument } from '../../shared/ui/ReportDocument'
import { ACADEMIC_STANDINGS, useAcademicReportCriteria } from './useAcademicReportCriteria'

export function StandingReportPage() {
  const { t } = useTranslation('academicStanding')
  const collection = useAcademicReportCriteria()
  const report = useStandingReport(collection.params)
  const summary = report.data?.data
  const selection = new URLSearchParams(collection.searchParams)
  selection.delete('page')
  selection.delete('per_page')
  const studentList = `/analytics/academic-standing/students?${selection.toString()}`
  const drill = (standing: string) => {
    const criteria = new URLSearchParams(selection)
    criteria.set('standing', standing)
    return `/analytics/academic-standing/students?${criteria.toString()}`
  }
  return (
    <ReportDocument title={t('report.title')} criteria={collection.criteria}>
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
          <Chart
            layout="horizontal"
            label={t('report.chart')}
            categoryLabel={t('report.standing')}
            valueLabel={t('report.students')}
            data={ACADEMIC_STANDINGS.map((standing) => ({
              label: t(`standing.values.${standing}`),
              value: summary.counts?.[standing] ?? 0,
              // A zero band outside an existing Standing criterion cannot broaden that selection.
              href: (summary.counts?.[standing] ?? 0) > 0 ? drill(standing) : undefined,
            }))}
          />
          <Stack direction="row" spacing={2} sx={{ '@media print': { display: 'none' } }}>
            <Button component={RouterLink} to={studentList}>
              {t('report.viewStudents')}
            </Button>
            <Button onClick={() => window.print()}>{t('report.print')}</Button>
          </Stack>
        </>
      )}
    </ReportDocument>
  )
}
