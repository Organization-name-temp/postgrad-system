import { Alert, AlertTitle, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useStatusTimeline } from '../../shared/api/generated/endpoints'

interface ReturnedNoticeProps {
  applicationId: number
}

/**
 * The banner shown at the top of the wizard when an application has been returned
 * for revision (ticket 07). It fetches the status timeline and surfaces the most
 * recent reviewer instruction — the note recorded on the move into
 * `returned_for_revision` — so the applicant knows exactly what to fix before they
 * edit their documents/program and resubmit.
 */
export function ReturnedNotice({ applicationId }: ReturnedNoticeProps) {
  const { t } = useTranslation('admissions')
  const timeline = useStatusTimeline(applicationId)

  // The latest transition into `returned_for_revision` carries the instruction. The
  // log is oldest-first, so scan from the end for the most recent one.
  const entries = timeline.data?.data ?? []
  const note = [...entries]
    .reverse()
    .find((entry) => entry.to_status === 'returned_for_revision')?.note

  return (
    <Alert severity="warning" sx={{ mb: 3 }}>
      <AlertTitle>{t('wizard.returnedTitle')}</AlertTitle>
      {t('wizard.returnedBody')}
      {note && (
        <Typography variant="body2" sx={{ mt: 1, fontWeight: 500 }}>
          {t('wizard.reviewerNote')}: {note}
        </Typography>
      )}
    </Alert>
  )
}
