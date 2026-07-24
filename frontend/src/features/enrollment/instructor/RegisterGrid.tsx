import CheckIcon from '@mui/icons-material/Check'
import CloseIcon from '@mui/icons-material/Close'
import {
  Button,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  ToggleButton,
  ToggleButtonGroup,
  Typography,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { courseStatusChip } from '../courseStatusChip'

/** One column of the register — a Class Session. */
export interface RegisterSession {
  id: number
  date: string
  weekday: number
  starts_at?: string
  ends_at?: string
  cancelled: boolean
}

/** One row of the register — an active Enrollment with its marks and derived attendance. */
export interface RegisterRosterRow {
  enrollment_id: number
  course_status: string
  student: { name?: string; university_id?: string | null }
  attendance: { recorded?: number; percentage?: number; at_risk?: boolean }
  /** session id (string key) → present. */
  marks: Record<string, boolean>
}

interface RegisterGridProps {
  sessions: RegisterSession[]
  roster: RegisterRosterRow[]
  /** Staged (unsaved) marks: `${sessionId}:${enrollmentId}` → present. */
  pending: Record<string, boolean>
  busy: boolean
  onToggleMark: (sessionId: number, enrollmentId: number, present: boolean) => void
  onToggleCancel: (session: RegisterSession) => void
}

export const markKey = (sessionId: number, enrollmentId: number) => `${sessionId}:${enrollmentId}`

/**
 * The attendance register grid (ticket 08): rows are the active roster, columns are the
 * Class Sessions. Each cell is a present/absent toggle (a cancelled session's column is
 * disabled and can be reinstated from its header); each row ends with the student's derived
 * attendance percentage and an audit-at-risk chip. Presentational and controlled — the page
 * owns the staged marks and the save/cancel mutations — so it is unit-testable in isolation.
 */
export function RegisterGrid({
  sessions,
  roster,
  pending,
  busy,
  onToggleMark,
  onToggleCancel,
}: RegisterGridProps) {
  const { t, i18n } = useTranslation('enrollment')

  const weekday = (n: number) => t(`weekdays.${n}`)
  const sessionLabel = (s: RegisterSession) =>
    `${weekday(s.weekday)} ${new Date(s.date).toLocaleDateString(i18n.language)}`

  return (
    <TableContainer sx={{ overflowX: 'auto' }}>
      <Table size="small" aria-label={t('attendance.title')}>
        <TableHead>
          <TableRow>
            <TableCell>{t('attendance.columns.student')}</TableCell>
            {sessions.map((session) => (
              <TableCell key={session.id} align="center">
                <Stack spacing={0.5} sx={{ alignItems: 'center' }}>
                  <Typography variant="caption" component="span">
                    {sessionLabel(session)}
                  </Typography>
                  {session.cancelled && (
                    <StatusChip
                      tone="neutral"
                      variant="outlined"
                      label={t('attendance.cancelledSession')}
                    />
                  )}
                  <Button
                    size="small"
                    color={session.cancelled ? 'primary' : 'warning'}
                    disabled={busy}
                    onClick={() => onToggleCancel(session)}
                    aria-label={t(
                      session.cancelled
                        ? 'attendance.reinstateSession'
                        : 'attendance.cancelSession',
                      {
                        date: sessionLabel(session),
                      },
                    )}
                  >
                    {t(session.cancelled ? 'attendance.reinstate' : 'attendance.cancel')}
                  </Button>
                </Stack>
              </TableCell>
            ))}
            <TableCell align="right">{t('attendance.columns.percentage')}</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {roster.map((row) => (
            <TableRow key={row.enrollment_id}>
              <TableCell>
                <Stack spacing={0.5} sx={{ alignItems: 'flex-start' }}>
                  <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
                    {row.student.name}
                  </Typography>
                  {row.student.university_id && (
                    <Typography variant="caption" component="span" color="text.secondary">
                      {row.student.university_id}
                    </Typography>
                  )}
                  {/* The registration mode label — Regular / Retake / Audit (user story 55). */}
                  <StatusChip
                    {...courseStatusChip(row.course_status, t(`courseStatus.${row.course_status}`))}
                  />
                </Stack>
              </TableCell>
              {sessions.map((session) => {
                const key = markKey(session.id, row.enrollment_id)
                const staged = pending[key]
                const saved = row.marks[String(session.id)]
                const value = staged ?? saved
                const current = value === undefined ? null : value ? 'present' : 'absent'

                return (
                  <TableCell key={session.id} align="center">
                    <ToggleButtonGroup
                      exclusive
                      size="small"
                      value={current}
                      disabled={session.cancelled || busy}
                      onChange={(_e, next: 'present' | 'absent' | null) => {
                        if (next !== null)
                          onToggleMark(session.id, row.enrollment_id, next === 'present')
                      }}
                    >
                      <ToggleButton
                        value="present"
                        color="success"
                        aria-label={t('attendance.markPresent', {
                          student: row.student.name ?? '',
                          date: sessionLabel(session),
                        })}
                      >
                        <CheckIcon fontSize="small" />
                      </ToggleButton>
                      <ToggleButton
                        value="absent"
                        color="error"
                        aria-label={t('attendance.markAbsent', {
                          student: row.student.name ?? '',
                          date: sessionLabel(session),
                        })}
                      >
                        <CloseIcon fontSize="small" />
                      </ToggleButton>
                    </ToggleButtonGroup>
                  </TableCell>
                )
              })}
              <TableCell align="right">
                <AttendanceSummary attendance={row.attendance} />
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  )
}

/** The row-end percentage plus an at-risk chip; a dash when no session is recorded yet. */
function AttendanceSummary({ attendance }: { attendance: RegisterRosterRow['attendance'] }) {
  const { t } = useTranslation('enrollment')

  if ((attendance.recorded ?? 0) === 0) {
    return (
      <Typography component="span" variant="body2" color="text.secondary">
        {t('attendance.none')}
      </Typography>
    )
  }

  return (
    <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'flex-end' }}>
      <Typography component="span" variant="body2">
        {t('attendance.value', { percent: attendance.percentage ?? 0 })}
      </Typography>
      {attendance.at_risk && (
        <StatusChip tone="warning" variant="filled-soft" label={t('attendance.atRisk')} />
      )}
    </Stack>
  )
}
