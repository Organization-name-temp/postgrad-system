import {
  Alert,
  Button,
  Chip,
  CircularProgress,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { Link as RouterLink } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { exportScreeningQueue, useScreeningQueue } from '../../../shared/api/generated/endpoints'
import type { ScreeningQueue200DataItem } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { FilterBar } from '../../../shared/ui/FilterBar'
import { DataToolbar } from '../../../shared/ui/DataToolbar'
import { useCollectionQuery } from '../../../shared/ui/useCollectionQuery'
import { downloadBlob } from '../../../shared/downloadBlob'

/**
 * The Student Affairs screening queue (ticket 07). Lists the applications awaiting
 * screening (`under_sa_review`), oldest-first, each with the applicant's identity,
 * chosen primary Program, and how long it has waited (the server's derived age).
 * The whole surface is gated on `application.screen` — the route and the API both
 * enforce it (ADR-0008). Opening a row goes to the review screen where the reviewer
 * acts on it.
 */
export function ScreeningQueuePage() {
  const { t, i18n } = useTranslation('admissions')
  const collection = useCollectionQuery()
  const params = {
    ...collection.params,
    age_days_min: collection.searchParams.has('age_days_min')
      ? Number(collection.searchParams.get('age_days_min'))
      : undefined,
    age_days_max: collection.searchParams.has('age_days_max')
      ? Number(collection.searchParams.get('age_days_max'))
      : undefined,
  }
  const { data, isPending, isError } = useScreeningQueue(params)
  const applications = data?.data ?? []

  const applicantName = (application: ScreeningQueue200DataItem): string =>
    pickLocaleName(application, i18n.language, application.national_id ?? '')

  const programName = (application: ScreeningQueue200DataItem): string => {
    const program = application.primary_program
    return program ? pickLocaleName(program, i18n.language, program.code ?? '—') : '—'
  }

  return (
    <>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('screening.title')}
      </Typography>

      <FilterBar
        values={collection.params}
        onChange={collection.setCriteria}
        fields={[
          { key: 'q', label: t('screening.filters.search') },
          { key: 'program_id', label: t('screening.filters.program') },
          { key: 'admission_cycle_id', label: t('screening.filters.cycle') },
          {
            key: 'degree_level',
            label: t('screening.columns.degree'),
            options: [
              { value: '', label: t('screening.filters.all') },
              { value: 'MSc', label: t('screening.filters.msc') },
              { value: 'PhD', label: t('screening.filters.phd') },
            ],
          },
          { key: 'age_days_min', label: t('screening.filters.minAge'), type: 'number' },
          { key: 'age_days_max', label: t('screening.filters.maxAge'), type: 'number' },
          {
            key: 'sort',
            label: t('screening.filters.sort'),
            options: [
              { value: '', label: t('screening.filters.oldest') },
              { value: '-age_days', label: t('screening.filters.ageDescending') },
              { value: 'age_days', label: t('screening.filters.ageAscending') },
              { value: 'name_en', label: t('screening.filters.nameEnglish') },
              { value: 'name_ar', label: t('screening.filters.nameArabic') },
            ],
          },
        ]}
      />
      {data?.meta && !isError && (
        <DataToolbar
          total={data.meta.total ?? 0}
          page={data.meta.current_page ?? params.page}
          lastPage={data.meta.last_page ?? 1}
          onPageChange={collection.setPage}
          onExport={async () =>
            downloadBlob(await exportScreeningQueue(params), 'applications.xlsx')
          }
        />
      )}
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('screening.intro')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('screening.loading')} sx={{ mt: 2 }} />}
      {isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('screening.loadError')}
        </Alert>
      )}

      {!isPending && !isError && applications.length === 0 && (
        <Typography color="text.secondary" sx={{ mt: 2 }}>
          {t('screening.empty')}
        </Typography>
      )}

      {applications.length > 0 && (
        <TableContainer sx={{ mt: 2 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('screening.columns.applicant')}</TableCell>
                <TableCell>{t('screening.columns.nationalId')}</TableCell>
                <TableCell>{t('screening.columns.degree')}</TableCell>
                <TableCell>{t('screening.columns.program')}</TableCell>
                <TableCell>{t('screening.columns.waiting')}</TableCell>
                <TableCell align="right">{t('screening.columns.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {applications.map((application) => (
                <TableRow key={application.id} hover>
                  <TableCell>{applicantName(application)}</TableCell>
                  <TableCell>
                    <bdi dir="ltr">{application.national_id}</bdi>
                  </TableCell>
                  <TableCell>{application.applying_for_degree}</TableCell>
                  <TableCell>{programName(application)}</TableCell>
                  <TableCell>
                    <Chip
                      size="small"
                      label={t('screening.waitingDays', { count: application.age_days ?? 0 })}
                    />
                  </TableCell>
                  <TableCell align="right">
                    <Button
                      component={RouterLink}
                      to={`/screening/${application.id}`}
                      size="small"
                      variant="outlined"
                    >
                      {t('screening.open')}
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}
    </>
  )
}
