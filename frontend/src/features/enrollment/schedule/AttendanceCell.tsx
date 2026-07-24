import { Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { StatusChip } from '../../../shared/ui/StatusChip'

/**
 * The minimal derived-attendance shape a schedule row shows (ticket 08). Kept structural so
 * both the Student's own My Schedule and the advisor/admin oversight read — two generated
 * types with the same wire shape — pass their `enrollment.attendance` here unchanged.
 */
export interface AttendanceInfo {
  recorded?: number | null
  percentage?: number | null
  at_risk?: boolean | null
}

/**
 * A derived attendance cell: the percentage, plus an at-risk chip when an Audit enrollment
 * has fallen below the audit minimum. "No sessions recorded yet" renders as a dash — the
 * server sends `recorded: 0` for that, never null. Shared by {@link SchedulePage} and the
 * student-schedule oversight view.
 */
export function AttendanceCell({ attendance }: { attendance?: AttendanceInfo | null }) {
  const { t } = useTranslation('enrollment')
  const recorded = attendance?.recorded ?? 0
  const atRisk = attendance?.at_risk ?? false

  if (recorded === 0) {
    return (
      <Typography component="span" variant="body2" color="text.secondary">
        {t('schedule.attendance.none')}
      </Typography>
    )
  }

  return (
    <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
      <Typography component="span" variant="body2">
        {t('schedule.attendance.value', { percent: attendance?.percentage ?? 0 })}
      </Typography>
      {atRisk && (
        <StatusChip tone="warning" variant="filled-soft" label={t('schedule.attendance.atRisk')} />
      )}
    </Stack>
  )
}
