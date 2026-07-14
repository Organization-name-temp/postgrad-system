import {
  Alert,
  Box,
  CircularProgress,
  List,
  ListItem,
  ListItemText,
  Stack,
  Step,
  StepLabel,
  Stepper,
  Typography,
} from '@mui/material'
import HistoryIcon from '@mui/icons-material/History'
import { useTranslation } from 'react-i18next'
import type { StatusTimeline200DataItem } from '../../shared/api/generated/endpoints'
import { ApplicationStatusChip } from './ApplicationStatusChip'
import { PROGRESS_STAGES, stageForStatus, toApplicationStatus } from './applicationStatus'

interface StatusTimelineProps {
  /** The append-only transition log, oldest-first (as the API returns it). */
  entries: StatusTimeline200DataItem[]
  /** The application's current status, driving the progress indicator. */
  currentStatus: string | undefined
  isPending: boolean
  isError: boolean
}

/**
 * The applicant's read-only status timeline (ticket 07) — the same shape as the
 * catalog's `VersionHistoryPanel`, over the append-only transition log. It shows a
 * coarse **progress indicator** (which workflow stage the application has reached)
 * and then each recorded transition, newest-first: the status entered (as an
 * {@see ApplicationStatusChip}), the reviewer's note where present, and when.
 * Presentational — the hosting view fetches the timeline and passes it in; the log
 * is append-only and has no write verbs here.
 */
export function StatusTimeline({
  entries,
  currentStatus,
  isPending,
  isError,
}: StatusTimelineProps) {
  const { t, i18n } = useTranslation('admissions')

  const dateFormatter = new Intl.DateTimeFormat(i18n.language, {
    dateStyle: 'medium',
    timeStyle: 'short',
  })

  const status = toApplicationStatus(currentStatus)
  const activeStage = status === null ? 0 : stageForStatus(status)

  // Newest-first — the most recent change is the most interesting (the log arrives
  // oldest-first, so reverse a copy without mutating the prop).
  const ordered = [...entries].reverse()

  return (
    <Box component="section" data-testid="status-timeline">
      {/* Progress indicator across the coarse workflow stages. */}
      <Stepper activeStep={activeStage} alternativeLabel sx={{ mb: 3 }}>
        {PROGRESS_STAGES.map((stage) => (
          <Step key={stage}>
            <StepLabel>{t(`timeline.stages.${stage}`)}</StepLabel>
          </Step>
        ))}
      </Stepper>

      <Typography
        variant="h5"
        component="h2"
        sx={{ mb: 1, display: 'flex', alignItems: 'center', gap: 1 }}
      >
        <HistoryIcon fontSize="small" />
        {t('timeline.title')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('timeline.loading')} />}
      {isError && <Alert severity="error">{t('timeline.loadError')}</Alert>}

      {!isPending && !isError && ordered.length === 0 && (
        <Typography color="text.secondary" variant="body2">
          {t('timeline.empty')}
        </Typography>
      )}

      {ordered.length > 0 && (
        <List data-testid="timeline-list">
          {ordered.map((entry) => (
            <ListItem key={entry.id} disableGutters sx={{ alignItems: 'flex-start' }}>
              <ListItemText
                disableTypography
                primary={
                  <Stack
                    direction="row"
                    spacing={1}
                    sx={{ alignItems: 'center', flexWrap: 'wrap' }}
                  >
                    <ApplicationStatusChip status={entry.to_status} />
                    <Typography variant="caption" color="text.secondary">
                      {entry.occurred_at ? dateFormatter.format(new Date(entry.occurred_at)) : ''}
                    </Typography>
                  </Stack>
                }
                secondary={
                  entry.note ? (
                    <Typography variant="body2" sx={{ mt: 0.5 }}>
                      {entry.note}
                    </Typography>
                  ) : undefined
                }
              />
            </ListItem>
          ))}
        </List>
      )}
    </Box>
  )
}
