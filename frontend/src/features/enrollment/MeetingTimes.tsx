import { Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'

interface Meeting {
  weekday?: number
  starts_at?: string
  ends_at?: string
}

/**
 * A cell of an Offering's weekly meeting times — the "To be scheduled" fallback when there
 * are none, else a stacked "Weekday HH:MM–HH:MM" line per slot. Shared by My Schedule and
 * the advisor review page so the meeting cell reads identically everywhere (design-system.md
 * §10: features colocate their own presentation). Weekdays localise through the enrollment
 * i18n namespace.
 */
export function MeetingTimes({ meetings }: { meetings: Meeting[] }) {
  const { t } = useTranslation('enrollment')

  if (meetings.length === 0) {
    return (
      <Typography component="span" variant="body2" color="text.secondary">
        {t('noMeetings')}
      </Typography>
    )
  }

  return (
    <Stack spacing={0.25}>
      {meetings.map((meeting) => (
        <Typography key={`${meeting.weekday}-${meeting.starts_at}`} variant="body2">
          {t(`weekdays.${meeting.weekday}`)} {meeting.starts_at}–{meeting.ends_at}
        </Typography>
      ))}
    </Stack>
  )
}
