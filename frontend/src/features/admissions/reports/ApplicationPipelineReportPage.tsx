import {
  Alert,
  Button,
  CircularProgress,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useApplicationPipelineReport } from '../../../shared/api/generated/endpoints'
import { Chart } from '../../../shared/ui/Chart'
import { FilterBar } from '../../../shared/ui/FilterBar'
import { useCollectionQuery } from '../../../shared/ui/useCollectionQuery'

export function ApplicationPipelineReportPage() {
  const { t } = useTranslation('admissions')
  const collection = useCollectionQuery()
  const cycle = collection.searchParams.get('admission_cycle_id')
  const params = {
    ...collection.params,
    admission_cycle_id: Number(cycle),
    program_id: collection.searchParams.has('program_id')
      ? Number(collection.searchParams.get('program_id'))
      : undefined,
  }
  const report = useApplicationPipelineReport(params, { query: { enabled: Boolean(cycle) } })
  const summary = report.data?.data
  const drill = (status: string) => {
    const criteria = new URLSearchParams()
    for (const key of ['admission_cycle_id', 'program_id', 'degree_level']) {
      const value = collection.searchParams.get(key)
      if (value) criteria.set(key, value)
    }
    criteria.set('status', status)
    return `/screening?${criteria.toString()}`
  }
  return (
    <Stack spacing={2}>
      <Typography component="h1" variant="h4">
        {t('pipeline.title')}
      </Typography>
      <FilterBar
        values={collection.params}
        onChange={collection.setCriteria}
        fields={[
          { key: 'admission_cycle_id', label: t('pipeline.cycle'), type: 'number' },
          { key: 'program_id', label: t('pipeline.program'), type: 'number' },
          {
            key: 'degree_level',
            label: t('screening.columns.degree'),
            options: [
              { value: '', label: t('screening.filters.all') },
              { value: 'MSc', label: t('screening.filters.msc') },
              { value: 'PhD', label: t('screening.filters.phd') },
            ],
          },
        ]}
      />
      {!cycle && <Typography>{t('pipeline.selectCycle')}</Typography>}
      {cycle && report.isPending && <CircularProgress aria-label={t('pipeline.loading')} />}
      {cycle && report.isError && (
        <Alert
          severity="error"
          action={<Button onClick={() => void report.refetch()}>{t('pipeline.retry')}</Button>}
        >
          {t('pipeline.loadError')}
        </Alert>
      )}
      {summary && (
        <>
          <Typography>{t('pipeline.total', { count: summary.total ?? 0 })}</Typography>
          <Chart
            label={t('pipeline.title')}
            categoryLabel={t('pipeline.stage')}
            valueLabel={t('pipeline.applications')}
            data={Object.entries(summary.statuses ?? {}).map(([status, value]) => ({
              label: t(`status.${status}`),
              value: value ?? 0,
              href: drill(status),
            }))}
          />
          <Table aria-label={t('pipeline.conversions')} size="small">
            <TableHead>
              <TableRow>
                <TableCell>{t('pipeline.from')}</TableCell>
                <TableCell>{t('pipeline.to')}</TableCell>
                <TableCell>{t('pipeline.eligible')}</TableCell>
                <TableCell>{t('pipeline.converted')}</TableCell>
                <TableCell>{t('pipeline.rate')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {summary.conversions?.map((edge) => (
                <TableRow key={`${edge.from_status}:${edge.to_status}`}>
                  <TableCell component="th" scope="row">
                    {t(`status.${edge.from_status}`)}
                  </TableCell>
                  <TableCell>{t(`status.${edge.to_status}`)}</TableCell>
                  <TableCell>
                    <bdi dir="ltr">{edge.eligible}</bdi>
                  </TableCell>
                  <TableCell>
                    <bdi dir="ltr">{edge.converted}</bdi>
                  </TableCell>
                  <TableCell>
                    {edge.rate == null ? (
                      t('pipeline.noDenominator')
                    ) : (
                      <bdi dir="ltr">{edge.rate}%</bdi>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </>
      )}
    </Stack>
  )
}
