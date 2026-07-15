import { Alert, AlertTitle, Box, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useStatusTimeline } from '../../shared/api/generated/endpoints'
import { ApplicationStatusChip } from './ApplicationStatusChip'
import { StatusTimeline } from './StatusTimeline'
import { InterviewPanel } from './interviews/InterviewPanel'

interface StatusViewProps {
  applicationId: number
  status: string | undefined
}

/**
 * The applicant's read-only status view (ticket 07), shown once their application
 * has left their hands and is progressing through review. It fetches the status
 * timeline for the application and renders the current status (as a chip), a
 * "submitted for review" reassurance, and the progress indicator + history
 * (via {@see StatusTimeline}). The workflow only advances by staff action, so there
 * is nothing to do here but watch — a reload always reflects the latest status.
 */
export function StatusView({ applicationId, status }: StatusViewProps) {
  const { t } = useTranslation('admissions')
  const timeline = useStatusTimeline(applicationId)

  return (
    <Box>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 2, flexWrap: 'wrap' }}>
        <Typography variant="h6" component="h2">
          {t('wizard.statusTitle')}
        </Typography>
        <ApplicationStatusChip status={status} />
      </Stack>

      <Alert severity="success" sx={{ mb: 3 }}>
        <AlertTitle>{t('wizard.underReviewTitle')}</AlertTitle>
        {t('wizard.underReviewBody')}
      </Alert>

      {/* Once the Dean approves for interview, the applicant confirms/reschedules here
          (ticket 09) — shown only in that status; every other in-review status is watch-only. */}
      {status === 'interview_scheduled' && <InterviewPanel />}

      <StatusTimeline
        entries={timeline.data?.data ?? []}
        currentStatus={status}
        isPending={timeline.isPending}
        isError={timeline.isError}
      />
    </Box>
  )
}
