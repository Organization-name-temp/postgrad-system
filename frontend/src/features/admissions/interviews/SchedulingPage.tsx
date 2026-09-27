import { useState } from 'react'
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Divider,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getBookedInterviewsQueryKey,
  exportApplicationsAwaitingInterviewScheduling,
  useApplicationsAwaitingInterviewScheduling,
  useBookedInterviews,
  useMarkAnInterviewComplete,
} from '../../../shared/api/generated/endpoints'
import type { ApplicationsAwaitingInterviewScheduling200DataItem } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { ScheduleDialog } from './ScheduleDialog'
import { formatSlotWhen } from './slotDisplay'
import { FilterBar } from '../../../shared/ui/FilterBar'
import { DataToolbar } from '../../../shared/ui/DataToolbar'
import { useCollectionQuery } from '../../../shared/ui/useCollectionQuery'
import { downloadBlob } from '../../../shared/downloadBlob'

/**
 * The Student Affairs interview-scheduling screen (features/admissions). Two worklists:
 * the Dean-approved applicants not yet booked (each schedulable into an open slot), and
 * the interviews already booked (each markable complete once it has happened, which
 * advances the application to `interview_completed`). Gated on `interview.schedule`.
 */
export function SchedulingPage() {
  const { t, i18n } = useTranslation('admissions')
  const queryClient = useQueryClient()

  const collection = useCollectionQuery(25, 'awaiting')
  const params = {
    ...collection.params,
    age_days_min: collection.searchParams.has('age_days_min')
      ? Number(collection.searchParams.get('age_days_min'))
      : undefined,
    age_days_max: collection.searchParams.has('age_days_max')
      ? Number(collection.searchParams.get('age_days_max'))
      : undefined,
  }
  const awaiting = useApplicationsAwaitingInterviewScheduling(params)
  const booked = useBookedInterviews()
  const complete = useMarkAnInterviewComplete()

  const [target, setTarget] = useState<
    ApplicationsAwaitingInterviewScheduling200DataItem | undefined
  >(undefined)

  const applicants = awaiting.data?.data ?? []
  const interviews = booked.data?.data ?? []

  const applicantName = (row: { name_en?: string; name_ar?: string }): string =>
    pickLocaleName(row, i18n.language, '—')

  const onComplete = (interviewId: number | undefined) => {
    if (interviewId === undefined) {
      return
    }
    complete.mutate(
      { interviewId },
      {
        onSuccess: () => queryClient.invalidateQueries({ queryKey: getBookedInterviewsQueryKey() }),
      },
    )
  }

  return (
    <>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('interviews.scheduling.title')}
      </Typography>

      <Box component="section" aria-labelledby="awaiting-scheduling-heading">
        <Divider id="awaiting-scheduling-heading" textAlign="left" sx={{ my: 2 }}>
          {t('interviews.scheduling.awaiting')}
        </Divider>
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
        {awaiting.data?.meta && !awaiting.isError && (
          <DataToolbar
            total={awaiting.data.meta.total ?? 0}
            page={awaiting.data.meta.current_page ?? params.page}
            lastPage={awaiting.data.meta.last_page ?? 1}
            onPageChange={collection.setPage}
            onExport={async () =>
              downloadBlob(
                await exportApplicationsAwaitingInterviewScheduling(params),
                'applications.xlsx',
              )
            }
          />
        )}

        {awaiting.isPending && <CircularProgress aria-label={t('interviews.scheduling.loading')} />}
        {awaiting.isError && <Alert severity="error">{t('interviews.scheduling.loadError')}</Alert>}
        {!awaiting.isPending && !awaiting.isError && applicants.length === 0 && (
          <Typography color="text.secondary">{t('interviews.scheduling.awaitingEmpty')}</Typography>
        )}
        {applicants.length > 0 && (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('screening.columns.applicant')}</TableCell>
                  <TableCell>{t('screening.columns.nationalId')}</TableCell>
                  <TableCell>{t('screening.columns.degree')}</TableCell>
                  <TableCell>{t('screening.columns.waiting')}</TableCell>
                  <TableCell align="right">{t('screening.columns.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {applicants.map((row) => (
                  <TableRow key={row.id} hover>
                    <TableCell>{applicantName(row)}</TableCell>
                    <TableCell>
                      <bdi dir="ltr">{row.national_id}</bdi>
                    </TableCell>
                    <TableCell>{row.applying_for_degree}</TableCell>
                    <TableCell>
                      <Chip
                        size="small"
                        label={t('screening.waitingDays', { count: row.age_days ?? 0 })}
                      />
                    </TableCell>
                    <TableCell align="right">
                      <Button size="small" variant="outlined" onClick={() => setTarget(row)}>
                        {t('interviews.scheduling.schedule')}
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        )}
      </Box>
      <Box component="section" aria-labelledby="booked-interviews-heading">
        <Divider id="booked-interviews-heading" textAlign="left" sx={{ my: 2, mt: 4 }}>
          {t('interviews.scheduling.booked')}
        </Divider>
        {complete.isError && (
          <Alert severity="error">{t('interviews.scheduling.completeError')}</Alert>
        )}
        {booked.isPending && <CircularProgress aria-label={t('interviews.scheduling.loading')} />}
        {booked.isError && <Alert severity="error">{t('interviews.scheduling.loadError')}</Alert>}
        {!booked.isPending && !booked.isError && interviews.length === 0 && (
          <Typography color="text.secondary">{t('interviews.scheduling.bookedEmpty')}</Typography>
        )}
        {interviews.length > 0 && (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('screening.columns.applicant')}</TableCell>
                  <TableCell>{t('interviews.slots.columns.when')}</TableCell>
                  <TableCell>{t('interviews.columns.confirmation')}</TableCell>
                  <TableCell>{t('interviews.columns.score')}</TableCell>
                  <TableCell align="right">{t('screening.columns.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {interviews.map((interview) => (
                  <TableRow key={interview.id} hover>
                    <TableCell>{applicantName(interview.applicant ?? {})}</TableCell>
                    <TableCell>
                      {formatSlotWhen(interview.slot?.scheduled_at, i18n.language)}
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="small"
                        color={interview.status === 'confirmed' ? 'success' : 'default'}
                        label={t(`interviews.status.${interview.status}`)}
                      />
                    </TableCell>
                    <TableCell>{interview.evaluation_score ?? '—'}</TableCell>
                    <TableCell align="right">
                      {interview.completed_at ? (
                        <Chip size="small" color="info" label={t('interviews.completed')} />
                      ) : (
                        <Button
                          size="small"
                          variant="contained"
                          onClick={() => onComplete(interview.id)}
                          disabled={complete.isPending}
                        >
                          {t('interviews.scheduling.markComplete')}
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        )}
      </Box>
      <ScheduleDialog
        open={target !== undefined}
        onClose={() => setTarget(undefined)}
        applicationId={target?.id}
        applicantName={target ? applicantName(target) : ''}
      />
    </>
  )
}
