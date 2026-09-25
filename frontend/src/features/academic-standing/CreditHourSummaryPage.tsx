import {
  Alert,
  Box,
  Skeleton,
  Paper,
  Chip,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
  Stack,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useMyTargetAwareCreditHourSummary } from '../../shared/api/generated/endpoints'
import { useMyCurrentAcademicStandingAndObservationHistory } from '../../shared/api/generated/endpoints'
import { useMyDerivedGraduationReadiness } from '../../shared/api/generated/endpoints'
import { useGetApiV1TerminationCasesMine } from '../../shared/api/generated/endpoints'
import { tabularNums } from '../../app/theme/fonts'
import { StatusChip } from '../../shared/ui/StatusChip'
import { standingPresentation } from './AcademicStandingCard'

const metrics = [
  'baseline_required_ch',
  'adjusted_required_ch',
  'attended_ch',
  'earned_ch',
  'transferred_ch',
  'waived_ch',
  'exempted_ch',
  'satisfied_ch',
  'remaining_ch',
] as const

type SummaryRow = { category?: string } & Partial<Record<(typeof metrics)[number], number>>

export function CreditHourSummaryPage() {
  const { t } = useTranslation('academicStanding')
  const { data, isPending, isError } = useMyTargetAwareCreditHourSummary()
  const standingQuery = useMyCurrentAcademicStandingAndObservationHistory()
  const readinessQuery = useMyDerivedGraduationReadiness()
  const terminationQuery = useGetApiV1TerminationCasesMine()
  const terminationCase = terminationQuery.data?.data as
    { state?: string; evidence?: Record<string, unknown> } | null | undefined
  const summary = data?.data
  const rows: SummaryRow[] = summary
    ? [...(summary.categories ?? []), ...(summary.total ? [summary.total] : [])]
    : []

  return (
    <Box>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('creditHours.title')}
      </Typography>
      <Typography color="text.secondary" sx={{ mb: 3 }}>
        {t('creditHours.intro')}
      </Typography>
      {!standingQuery.isPending && !standingQuery.isError && standingQuery.data?.data && (
        <Paper variant="outlined" sx={{ p: 2, mb: 3 }}>
          <Stack spacing={1} sx={{ alignItems: 'flex-start' }}>
            <Typography variant="h6" component="h2">
              {t('standing.title')}
            </Typography>
            <StatusChip
              {...standingPresentation(standingQuery.data.data.standing)}
              variant="filled-soft"
              label={t(`standing.values.${standingQuery.data.data.standing}`)}
            />
            <Typography color="text.secondary">
              {t(`standing.explanations.${standingQuery.data.data.explanation}`)}
            </Typography>
            {standingQuery.data.data.registration_ceiling_ch != null && (
              <Typography>
                {t('standing.ceiling', { count: standingQuery.data.data.registration_ceiling_ch })}
              </Typography>
            )}
          </Stack>
        </Paper>
      )}
      {!readinessQuery.isPending && !readinessQuery.isError && readinessQuery.data?.data && (
        <Paper variant="outlined" sx={{ p: 2, mb: 3 }}>
          <Stack spacing={1} sx={{ alignItems: 'flex-start' }}>
            <Typography variant="h6" component="h2">
              {t('readiness.title')}
            </Typography>
            <Chip
              color={readinessQuery.data.data.ready ? 'success' : 'warning'}
              label={t(readinessQuery.data.data.ready ? 'readiness.ready' : 'readiness.notReady')}
            />
            <Typography>
              {t('readiness.remaining', {
                count: readinessQuery.data.data.remaining_credit_hours ?? 0,
              })}
            </Typography>
            <Typography>
              {t('readiness.cgpa', { value: readinessQuery.data.data.cgpa?.toFixed(2) ?? '—' })}
            </Typography>
            {(readinessQuery.data.data.blockers ?? []).map((blocker) => (
              <Alert key={blocker} severity="info">
                {t(`readiness.blockers.${blocker}`)}
              </Alert>
            ))}
            <Typography color="text.secondary">{t('readiness.completingSchedule')}</Typography>
          </Stack>
        </Paper>
      )}
      {terminationCase && (
        <Alert severity="error" sx={{ mb: 3 }}>
          <Typography variant="subtitle1">{t('termination.title')}</Typography>
          <Typography>{t(`termination.states.${terminationCase.state}`)}</Typography>
          <Typography variant="body2">{t('termination.explanation')}</Typography>
        </Alert>
      )}
      {isPending && (
        <Stack spacing={1} aria-label={t('creditHours.loading')}>
          <Skeleton variant="rounded" height={48} />
          <Skeleton variant="rounded" height={44} />
          <Skeleton variant="rounded" height={44} />
          <Skeleton variant="rounded" height={44} />
        </Stack>
      )}
      {isError && <Alert severity="error">{t('creditHours.loadError')}</Alert>}
      {!isPending && !isError && (
        <TableContainer component={Paper} variant="outlined">
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>{t('creditHours.category')}</TableCell>
                {metrics.map((metric) => (
                  <TableCell key={metric} align="center">
                    {t(`creditHours.metrics.${metric}`)}
                  </TableCell>
                ))}
              </TableRow>
            </TableHead>
            <TableBody>
              {rows.map((row) => (
                <TableRow
                  key={row.category}
                  sx={row.category === 'Total' ? { '& th, & td': { fontWeight: 700 } } : undefined}
                >
                  <TableCell component="th" scope="row">
                    {t(`creditHours.categories.${row.category}`)}
                  </TableCell>
                  {metrics.map((metric) => (
                    <TableCell key={metric} align="center" sx={tabularNums}>
                      {row[metric] ?? 0}
                    </TableCell>
                  ))}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}
      <Typography variant="body2" color="text.secondary" sx={{ mt: 2 }}>
        {t('creditHours.explanation')}
      </Typography>
    </Box>
  )
}
