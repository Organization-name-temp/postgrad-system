import { useState } from 'react'
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
import { useTranslation } from 'react-i18next'
import {
  exportAssignedInterviews,
  useInterviewsAssignedToMe,
} from '../../../shared/api/generated/endpoints'
import type { InterviewsAssignedToMe200DataItem } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { FilterBar } from '../../../shared/ui/FilterBar'
import { DataToolbar } from '../../../shared/ui/DataToolbar'
import { useCollectionQuery } from '../../../shared/ui/useCollectionQuery'
import { downloadBlob } from '../../../shared/downloadBlob'
import { EvaluationDialog } from './EvaluationDialog'
import { formatSlotWhen, slotWhere } from './slotDisplay'

/**
 * The interviewer's queue (features/admissions): the interviews booked into the
 * signed-in faculty member's own slots, each with the applicant, the when/where, and
 * an evaluation entry. The evaluation is row-level authorized server-side (only the
 * assigned interviewer may save); this screen is reachable by any faculty member and
 * simply shows an empty queue for someone with no assigned interviews.
 */
export function InterviewerPage() {
  const { t, i18n } = useTranslation('admissions')
  const collection = useCollectionQuery()
  const params = {
    ...collection.params,
    evaluation_score_min: collection.searchParams.has('evaluation_score_min')
      ? Number(collection.searchParams.get('evaluation_score_min'))
      : undefined,
    evaluation_score_max: collection.searchParams.has('evaluation_score_max')
      ? Number(collection.searchParams.get('evaluation_score_max'))
      : undefined,
  }
  const { data, isPending, isError } = useInterviewsAssignedToMe(params)
  const interviews = data?.data ?? []

  const [target, setTarget] = useState<InterviewsAssignedToMe200DataItem | undefined>(undefined)

  return (
    <>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('interviews.mine.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('interviews.mine.intro')}
      </Typography>

      <FilterBar
        values={collection.params}
        onChange={collection.setCriteria}
        fields={[
          { key: 'q', label: t('screening.filters.search') },
          {
            key: 'status',
            label: t('interviews.columns.confirmation'),
            options: [
              { value: '', label: t('interviews.filters.allStates') },
              { value: 'pending_confirmation', label: t('interviews.status.pending_confirmation') },
              { value: 'confirmed', label: t('interviews.status.confirmed') },
            ],
          },
          {
            key: 'mode',
            label: t('interviews.filters.mode'),
            options: [
              { value: '', label: t('interviews.filters.allModes') },
              { value: 'in_person', label: t('interviews.modes.in_person') },
              { value: 'virtual', label: t('interviews.modes.virtual') },
            ],
          },
          { key: 'scheduled_at_min', label: t('interviews.filters.from'), type: 'date' },
          { key: 'scheduled_at_max', label: t('interviews.filters.to'), type: 'date' },
          { key: 'evaluation_score_min', label: t('interviews.filters.minScore'), type: 'number' },
          { key: 'evaluation_score_max', label: t('interviews.filters.maxScore'), type: 'number' },
          { key: 'sort', label: t('screening.filters.sort') },
        ]}
      />
      {data?.meta && (
        <DataToolbar
          total={data.meta.total ?? 0}
          page={data.meta.current_page ?? params.page}
          lastPage={data.meta.last_page ?? 1}
          onPageChange={collection.setPage}
          onExport={async () =>
            downloadBlob(await exportAssignedInterviews(params), 'assigned-interviews.xlsx')
          }
        />
      )}

      {isPending && <CircularProgress aria-label={t('interviews.mine.loading')} sx={{ mt: 2 }} />}
      {isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('interviews.mine.loadError')}
        </Alert>
      )}
      {!isPending && !isError && interviews.length === 0 && (
        <Typography color="text.secondary" sx={{ mt: 2 }}>
          {t('interviews.mine.empty')}
        </Typography>
      )}

      {interviews.length > 0 && (
        <TableContainer sx={{ mt: 2 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('screening.columns.applicant')}</TableCell>
                <TableCell>{t('interviews.slots.columns.when')}</TableCell>
                <TableCell>{t('interviews.slots.columns.where')}</TableCell>
                <TableCell>{t('interviews.columns.score')}</TableCell>
                <TableCell align="right">{t('screening.columns.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {interviews.map((interview) => (
                <TableRow key={interview.id} hover>
                  <TableCell>
                    {pickLocaleName(interview.applicant ?? {}, i18n.language, '—')}
                  </TableCell>
                  <TableCell>
                    {formatSlotWhen(interview.slot?.scheduled_at, i18n.language)}
                  </TableCell>
                  <TableCell>{interview.slot ? (slotWhere(interview.slot) ?? '—') : '—'}</TableCell>
                  <TableCell>
                    {interview.evaluation_score ?? (
                      <Chip size="small" label={t('interviews.evaluation.none')} />
                    )}
                  </TableCell>
                  <TableCell align="right">
                    <Button
                      size="small"
                      variant="outlined"
                      onClick={() => setTarget(interview)}
                      disabled={interview.completed_at !== null}
                    >
                      {interview.evaluation_score !== null &&
                      interview.evaluation_score !== undefined
                        ? t('interviews.evaluation.editAction')
                        : t('interviews.evaluation.recordAction')}
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      <EvaluationDialog
        open={target !== undefined}
        onClose={() => setTarget(undefined)}
        interview={target}
      />
    </>
  )
}
