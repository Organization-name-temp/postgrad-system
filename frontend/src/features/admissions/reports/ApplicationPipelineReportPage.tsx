import {
  Alert,
  Button,
  CircularProgress,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useState } from 'react'
import {
  useApplicationPipelineReport,
  useListAdmissionCycles,
  useListPrograms,
} from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { Chart } from '../../../shared/ui/Chart'
import { FilterBar } from '../../../shared/ui/FilterBar'
import { ReportDocument } from '../../../shared/ui/ReportDocument'
import { useCollectionQuery } from '../../../shared/ui/useCollectionQuery'

export function ApplicationPipelineReportPage() {
  const { t, i18n } = useTranslation('admissions')
  const [cycleSearch, setCycleSearch] = useState<string | null>(null)
  const [programSearch, setProgramSearch] = useState<string | null>(null)
  const collection = useCollectionQuery()
  const cycle = collection.searchParams.get('admission_cycle_id')
  const program = collection.searchParams.get('program_id')
  const cycles = useListAdmissionCycles({ q: cycleSearch ?? undefined, per_page: 100 })
  const programs = useListPrograms({ q: programSearch ?? undefined, per_page: 100 })
  const selectedCycle = useListAdmissionCycles(
    { id: cycle ?? undefined, per_page: 100 },
    { query: { enabled: Boolean(cycle) } },
  )
  const selectedProgram = useListPrograms(
    { id: program ?? undefined, per_page: 100 },
    { query: { enabled: Boolean(program) } },
  )
  const cycleOption = (row: { id?: number; name_en?: string; name_ar?: string }) => ({
    value: String(row.id),
    label: pickLocaleName(row, i18n.language, String(row.id)),
  })
  const programOption = (row: {
    id?: number
    code?: string
    name_en?: string
    name_ar?: string
  }) => ({
    value: String(row.id),
    label: pickLocaleName(row, i18n.language, row.code ?? String(row.id)),
  })
  const cycleRow = selectedCycle.data?.data?.find((row) => String(row.id) === cycle)
  const programRow = selectedProgram.data?.data?.find((row) => String(row.id) === program)
  const currentCycle = cycleRow
    ? cycleOption(cycleRow)
    : cycle
      ? { value: cycle, label: cycle }
      : undefined
  const currentProgram = programRow
    ? programOption(programRow)
    : program
      ? { value: program, label: program }
      : undefined
  const degree = collection.searchParams.get('degree_level')
  const referenceCopy = {
    loadingText: t('pipeline.referenceLoading'),
    noOptionsText: t('pipeline.noMatches'),
    clearText: t('pipeline.clear'),
    openText: t('pipeline.open'),
    closeText: t('pipeline.close'),
  }
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
    <ReportDocument
      title={t('pipeline.title')}
      criteria={[
        { label: t('pipeline.cycle'), value: currentCycle?.label ?? t('pipeline.selectCycle') },
        { label: t('pipeline.program'), value: currentProgram?.label ?? t('pipeline.allPrograms') },
        {
          label: t('screening.columns.degree'),
          value:
            degree === 'MSc'
              ? t('screening.filters.msc')
              : degree === 'PhD'
                ? t('screening.filters.phd')
                : (degree ?? t('screening.filters.all')),
        },
      ]}
    >
      <FilterBar
        values={collection.params}
        onChange={collection.setCriteria}
        fields={[
          {
            key: 'admission_cycle_id',
            label: t('pipeline.cycle'),
            reference: {
              ...referenceCopy,
              options: (cycles.data?.data ?? []).map(cycleOption),
              selected: currentCycle,
              query: cycleSearch,
              onSearch: setCycleSearch,
              loading: cycles.isPending,
            },
          },
          {
            key: 'program_id',
            label: t('pipeline.program'),
            reference: {
              ...referenceCopy,
              options: (programs.data?.data ?? []).map(programOption),
              selected: currentProgram,
              query: programSearch,
              onSearch: setProgramSearch,
              loading: programs.isPending,
            },
          },
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
            layout="horizontal"
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
    </ReportDocument>
  )
}
