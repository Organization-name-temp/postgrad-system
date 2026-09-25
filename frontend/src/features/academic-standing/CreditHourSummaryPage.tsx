import {
  Alert,
  Box,
  Button,
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
import { useGetApiV1RegistrationRemediationObligations } from '../../shared/api/generated/endpoints'
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
  const { data, isPending, isError, refetch } = useMyTargetAwareCreditHourSummary()
  const standingQuery = useMyCurrentAcademicStandingAndObservationHistory()
  const readinessQuery = useMyDerivedGraduationReadiness()
  const terminationQuery = useGetApiV1TerminationCasesMine()
  const remediationQuery = useGetApiV1RegistrationRemediationObligations()
  const terminationCase = terminationQuery.data?.data
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
      {standingQuery.isPending && (
        <Skeleton
          variant="rounded"
          height={120}
          aria-label={t('standing.loading')}
          sx={{ mb: 3 }}
        />
      )}
      {standingQuery.isError && (
        <Alert
          severity="error"
          action={
            <Button onClick={() => void standingQuery.refetch()}>{t('creditHours.retry')}</Button>
          }
          sx={{ mb: 3 }}
        >
          {t('standing.loadError')}
        </Alert>
      )}
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
      {readinessQuery.isPending && (
        <Skeleton
          variant="rounded"
          height={160}
          aria-label={t('readiness.loading')}
          sx={{ mb: 3 }}
        />
      )}
      {readinessQuery.isError && (
        <Alert
          severity="error"
          action={
            <Button onClick={() => void readinessQuery.refetch()}>{t('creditHours.retry')}</Button>
          }
          sx={{ mb: 3 }}
        >
          {t('readiness.loadError')}
        </Alert>
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
      {terminationQuery.isPending && (
        <Skeleton
          variant="rounded"
          height={110}
          aria-label={t('termination.loading')}
          sx={{ mb: 3 }}
        />
      )}
      {terminationQuery.isError && (
        <Alert
          severity="error"
          action={
            <Button onClick={() => void terminationQuery.refetch()}>
              {t('creditHours.retry')}
            </Button>
          }
          sx={{ mb: 3 }}
        >
          {t('termination.loadError')}
        </Alert>
      )}
      {!terminationQuery.isPending && !terminationQuery.isError && !terminationCase && (
        <Alert severity="info" sx={{ mb: 3 }}>
          {t('termination.empty')}
        </Alert>
      )}
      {terminationCase && (
        <Alert
          severity={
            terminationCase.state === 'Terminated'
              ? 'error'
              : ['Dismissed', 'TriggerCleared', 'EvidenceChanged'].includes(
                    terminationCase.state ?? '',
                  )
                ? 'info'
                : 'warning'
          }
          sx={{ mb: 3 }}
        >
          <Typography variant="subtitle1">{t('termination.title')}</Typography>
          <Typography>{t(`termination.states.${terminationCase.state}`)}</Typography>
          <Typography variant="body2">{t('termination.explanation')}</Typography>
          {terminationCase.advisor_assessment && (
            <Typography>
              {t('termination.history.advisor', { value: terminationCase.advisor_assessment })}
            </Typography>
          )}
          {terminationCase.department_head_reason && (
            <Typography>
              {t('termination.history.departmentHead', {
                value: terminationCase.department_head_reason,
              })}
            </Typography>
          )}
          {terminationCase.council_reason && (
            <Typography>
              {t('termination.history.council', { value: terminationCase.council_reason })}
            </Typography>
          )}
        </Alert>
      )}
      {remediationQuery.isPending && (
        <Skeleton
          variant="rounded"
          height={110}
          aria-label={t('remediation.loading')}
          sx={{ mb: 3 }}
        />
      )}
      {remediationQuery.isError && (
        <Alert
          severity="error"
          action={
            <Button onClick={() => void remediationQuery.refetch()}>
              {t('creditHours.retry')}
            </Button>
          }
          sx={{ mb: 3 }}
        >
          {t('remediation.loadError')}
        </Alert>
      )}
      {!remediationQuery.isPending &&
        !remediationQuery.isError &&
        (remediationQuery.data?.data?.length ?? 0) === 0 && (
          <Alert severity="info" sx={{ mb: 3 }}>
            {t('remediation.empty')}
          </Alert>
        )}
      {!remediationQuery.isPending &&
        !remediationQuery.isError &&
        (remediationQuery.data?.data?.length ?? 0) > 0 && (
          <Paper variant="outlined" sx={{ p: 2, mb: 3 }}>
            <Typography variant="h6" component="h2" gutterBottom>
              {t('remediation.title')}
            </Typography>
            <Typography color="text.secondary" sx={{ mb: 1 }}>
              {t('remediation.intro')}
            </Typography>
            <Stack spacing={1}>
              {(remediationQuery.data?.data ?? []).map((item) => (
                <Alert
                  key={item.id}
                  severity={
                    ['Satisfied', 'ReleasedByExemption'].includes(item.state ?? '')
                      ? 'success'
                      : 'warning'
                  }
                >
                  {t('remediation.item', {
                    course: item.course_id,
                    state: t(`remediation.states.${item.state}`),
                    deadline: item.effective_deadline ?? t('remediation.noDeadline'),
                  })}
                </Alert>
              ))}
            </Stack>
          </Paper>
        )}
      {isPending && (
        <Stack spacing={1} aria-label={t('creditHours.loading')}>
          <Skeleton variant="rounded" height={48} />
          <Skeleton variant="rounded" height={44} />
          <Skeleton variant="rounded" height={44} />
          <Skeleton variant="rounded" height={44} />
        </Stack>
      )}
      {isError && (
        <Alert
          severity="error"
          action={<Button onClick={() => void refetch()}>{t('creditHours.retry')}</Button>}
        >
          {t('creditHours.loadError')}
        </Alert>
      )}
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
