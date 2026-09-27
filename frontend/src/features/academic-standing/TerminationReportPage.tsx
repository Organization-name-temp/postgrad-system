import { Alert, Button, CircularProgress, Stack, Typography } from '@mui/material'
import { Link as RouterLink } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useScopedTerminationCaseCountsAndDistinctStudents as useTerminationReport } from '../../shared/api/generated/endpoints'
import { Chart } from '../../shared/ui/Chart'
import { FilterBar } from '../../shared/ui/FilterBar'
import { ReportDocument } from '../../shared/ui/ReportDocument'
import {
  TERMINATION_REPORT_STATES,
  useTerminationReportCriteria,
} from './useTerminationReportCriteria'

export function TerminationReportPage() {
  const { t } = useTranslation('academicStanding')
  const collection = useTerminationReportCriteria()
  const report = useTerminationReport(collection.params)
  const summary = report.data?.data
  const selection = new URLSearchParams(collection.searchParams)
  selection.delete('page')
  selection.delete('per_page')
  const drill = (state: string) => {
    const query = new URLSearchParams(selection)
    query.set('state', state)
    return `/analytics/termination/cases?${query.toString()}`
  }
  return (
    <ReportDocument title={t('terminationReport.title')} criteria={collection.criteria}>
      <Typography>{t('terminationReport.intro')}</Typography>
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
          <Typography>
            {t('terminationReport.population', { count: summary.case_count ?? 0 })}
          </Typography>
          <Typography>
            {t('terminationReport.students', { count: summary.student_count ?? 0 })}
          </Typography>
          {summary.case_count === 0 && <Alert severity="info">{t('report.empty')}</Alert>}
          <Chart
            layout="horizontal"
            label={t('terminationReport.chart')}
            categoryLabel={t('terminationReport.state')}
            valueLabel={t('terminationReport.cases')}
            data={TERMINATION_REPORT_STATES.map((state) => ({
              label: t(`terminationReport.states.${state}`),
              value: summary.states?.[state] ?? 0,
              href: (summary.states?.[state] ?? 0) > 0 ? drill(state) : undefined,
            }))}
          />
          <Stack direction="row" spacing={2} sx={{ '@media print': { display: 'none' } }}>
            <Button
              component={RouterLink}
              to={`/analytics/termination/cases?${selection.toString()}`}
            >
              {t('terminationReport.viewCases')}
            </Button>
            <Button onClick={() => window.print()}>{t('report.print')}</Button>
          </Stack>
        </>
      )}
    </ReportDocument>
  )
}
