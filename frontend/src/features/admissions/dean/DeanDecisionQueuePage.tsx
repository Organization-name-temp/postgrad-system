import {
  Alert,
  Button,
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
import {
  exportDeanDecisionWorklist,
  useDeanDecisionsQueue,
} from '../../../shared/api/generated/endpoints'
import type { DeanDecisionsQueue200DataItem } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { FilterBar } from '../../../shared/ui/FilterBar'
import { DataToolbar } from '../../../shared/ui/DataToolbar'
import { useCollectionQuery } from '../../../shared/ui/useCollectionQuery'
import { downloadBlob } from '../../../shared/downloadBlob'
import { ApplicationStatusChip } from '../ApplicationStatusChip'

/**
 * The Dean's admission-decisions queue (ticket 12). Lists the applications awaiting the
 * Dean's final decision after interview — those resting at `interview_completed` and the
 * held `conditionally_accepted` / `waitlisted` offers — for the signed-in Dean's
 * faculties, oldest-first. The faculty scope is enforced server-side (a Dean with no
 * assignment simply sees an empty queue). Gated on `applicant.decide` (route + API).
 * Opening a row goes to the decision screen.
 */
export function DeanDecisionQueuePage() {
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
  const { data, isPending, isError } = useDeanDecisionsQueue(params)
  const applications = data?.data ?? []

  const applicantName = (application: DeanDecisionsQueue200DataItem): string =>
    pickLocaleName(application, i18n.language, application.national_id ?? '')

  const programName = (application: DeanDecisionsQueue200DataItem): string => {
    const program = application.primary_program
    return program ? pickLocaleName(program, i18n.language, program.code ?? '—') : '—'
  }

  return (
    <>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('decisions.title')}
      </Typography>
      <FilterBar
        values={collection.params}
        onChange={collection.setCriteria}
        fields={[
          {
            key: 'status',
            label: t('decisions.filters.state'),
            options: [
              { value: '', label: t('decisions.filters.allStates') },
              ...['interview_completed', 'conditionally_accepted', 'waitlisted'].map((status) => ({
                value: status,
                label: t(`status.${status}`),
              })),
            ],
          },
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
            downloadBlob(await exportDeanDecisionWorklist(params), 'applications.xlsx')
          }
        />
      )}
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('decisions.intro')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('decisions.loading')} sx={{ mt: 2 }} />}
      {isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('decisions.loadError')}
        </Alert>
      )}

      {!isPending && !isError && applications.length === 0 && (
        <Typography color="text.secondary" sx={{ mt: 2 }}>
          {t('decisions.empty')}
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
                <TableCell>{t('deanReview.decide')}</TableCell>
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
                    <ApplicationStatusChip status={application.status} />
                  </TableCell>
                  <TableCell align="right">
                    <Button
                      component={RouterLink}
                      to={`/dean/decisions/${application.id}`}
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
