import { useState } from 'react'
import {
  Alert,
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Paper,
  Stack,
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
  getTheActingStudentsScheduleTheirMintedEnrollmentsGroupedBySemesterQueryKey,
  useFreeDropACourseFromTheScheduleadddropPeriod,
  useTheActingStudentsScheduleTheirMintedEnrollmentsGroupedBySemester,
  useWithdrawFromACoursewithdrawalPeriod,
} from '../../../shared/api/generated/endpoints'
import type {
  TheActingStudentsScheduleTheirMintedEnrollmentsGroupedBySemester200DataItem as ScheduleGroup,
  TheActingStudentsScheduleTheirMintedEnrollmentsGroupedBySemester200DataItemEnrollmentsItem as ScheduleEnrollment,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StatusChip } from '../../../shared/ui/StatusChip'
import type { EligibleCourse } from '../registration/classification'
import { courseStatusChip } from '../courseStatusChip'
import { MeetingTimes } from '../MeetingTimes'
import { AddCoursePanel } from './AddCoursePanel'
import { AttendanceCell } from './AttendanceCell'

/** A per-enrollment action awaiting confirmation. */
type PendingAction = { enrollment: ScheduleEnrollment; kind: 'drop' | 'withdraw' }

/**
 * The Student's My Schedule (features/enrollment): their minted Enrollments grouped by
 * Semester, each with its Course Status chip, classification, meeting times and credit
 * hours (ticket 06). Ticket 07 adds the period-gated changes in context — a per-semester
 * banner naming the current period, a Drop/Withdraw button on each enrollment shown only
 * inside its window, and an add-a-course panel during add/drop. Every button mirrors the
 * server's window flags for UX; the API re-checks and stays authoritative. Persona-guarded
 * to R02; the API re-enforces `courses.register_self`.
 */
export function SchedulePage() {
  const { t } = useTranslation('enrollment')
  const queryClient = useQueryClient()
  const { data, isPending, isError } =
    useTheActingStudentsScheduleTheirMintedEnrollmentsGroupedBySemester()
  const groups = data?.data ?? []

  const onChanged = () =>
    queryClient.invalidateQueries({
      queryKey: getTheActingStudentsScheduleTheirMintedEnrollmentsGroupedBySemesterQueryKey(),
    })

  return (
    <>
      <Typography variant="h4" component="h1" sx={{ mb: 3 }}>
        {t('schedule.title')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('schedule.loading')} />}
      {isError && <Alert severity="error">{t('schedule.loadError')}</Alert>}

      {!isPending && !isError && groups.length === 0 && (
        <Typography color="text.secondary">{t('schedule.empty')}</Typography>
      )}

      {groups.map((group, index) => (
        <SemesterCard key={group.semester?.id ?? index} group={group} onChanged={onChanged} />
      ))}
    </>
  )
}

/** One Semester's Enrollments as a card — the period banner, the table, and (in add/drop) the add panel. */
function SemesterCard({ group, onChanged }: { group: ScheduleGroup; onChanged: () => void }) {
  const { t, i18n } = useTranslation('enrollment')
  const semester = group.semester
  const enrollments = group.enrollments ?? []
  const headingId = `semester-${semester?.id ?? 'x'}-heading`
  const semesterName = semester ? pickLocaleName(semester, i18n.language) : ''
  const totalCredits = enrollments.reduce((sum, e) => sum + (e.credit_hours ?? 0), 0)

  const canFreeChange = group.can_free_change ?? false
  const canWithdraw = group.can_withdraw ?? false
  const showActions = canFreeChange || canWithdraw
  const period = group.current_period ?? 'closed'
  const addable = (group.addable_courses ?? []) as unknown as EligibleCourse[]

  const [pending, setPending] = useState<PendingAction | null>(null)
  const drop = useFreeDropACourseFromTheScheduleadddropPeriod()
  const withdraw = useWithdrawFromACoursewithdrawalPeriod()
  const acting = drop.isPending || withdraw.isPending
  const actionError = drop.error ?? withdraw.error

  const confirm = async () => {
    if (!pending?.enrollment.id) return
    const enrollmentId = pending.enrollment.id
    if (pending.kind === 'drop') {
      await drop.mutateAsync({ enrollmentId })
    } else {
      await withdraw.mutateAsync({ enrollmentId })
    }
    setPending(null)
    onChanged()
  }

  const closeDialog = () => {
    setPending(null)
    drop.reset()
    withdraw.reset()
  }

  return (
    <Paper
      component="section"
      aria-labelledby={headingId}
      variant="outlined"
      sx={{ borderRadius: 3, p: { xs: 2, sm: 3 }, mb: 3 }}
    >
      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        spacing={1}
        sx={{ justifyContent: 'space-between', alignItems: { sm: 'center' }, mb: 2 }}
      >
        <Typography id={headingId} variant="h6" component="h2">
          {semesterName}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {t('schedule.totalCredits', { count: totalCredits })}
        </Typography>
      </Stack>

      <Alert severity="info" sx={{ mb: 2 }} icon={false}>
        <Typography component="span" variant="body2" sx={{ fontWeight: 600 }}>
          {t('schedule.period.label')}:
        </Typography>{' '}
        {t(`schedule.period.${period}`)}
      </Alert>

      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>{t('schedule.columns.course')}</TableCell>
              <TableCell>{t('schedule.columns.status')}</TableCell>
              <TableCell>{t('schedule.columns.classification')}</TableCell>
              <TableCell>{t('schedule.columns.meetings')}</TableCell>
              <TableCell align="right">{t('schedule.columns.creditHours')}</TableCell>
              <TableCell>{t('schedule.columns.attendance')}</TableCell>
              {showActions && <TableCell align="right">{t('schedule.columns.actions')}</TableCell>}
            </TableRow>
          </TableHead>
          <TableBody>
            {enrollments.map((enrollment) => (
              <EnrollmentRow
                key={enrollment.id}
                enrollment={enrollment}
                showActions={showActions}
                canDrop={canFreeChange}
                canWithdraw={canWithdraw}
                disabled={acting}
                onDrop={() => setPending({ enrollment, kind: 'drop' })}
                onWithdraw={() => setPending({ enrollment, kind: 'withdraw' })}
              />
            ))}
          </TableBody>
        </Table>
      </TableContainer>

      {canFreeChange && <AddCoursePanel courses={addable} onAdded={onChanged} />}

      <ConfirmActionDialog
        pending={pending}
        acting={acting}
        error={actionError}
        onConfirm={confirm}
        onCancel={closeDialog}
      />
    </Paper>
  )
}

interface EnrollmentRowProps {
  enrollment: ScheduleEnrollment
  showActions: boolean
  canDrop: boolean
  canWithdraw: boolean
  disabled: boolean
  onDrop: () => void
  onWithdraw: () => void
}

function EnrollmentRow({
  enrollment,
  showActions,
  canDrop,
  canWithdraw,
  disabled,
  onDrop,
  onWithdraw,
}: EnrollmentRowProps) {
  const { t, i18n } = useTranslation('enrollment')
  const course = enrollment.course
  const name = course ? pickLocaleName(course, i18n.language) : ''
  const meetings = enrollment.meetings ?? []
  const status = enrollment.course_status ?? 'Regular'
  // A withdrawn enrollment is a permanent record — no further self-serve action on it.
  const isWithdrawn = status === 'Withdrawn'

  return (
    <TableRow>
      <TableCell>
        <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
          {course?.code}
        </Typography>{' '}
        {name}
      </TableCell>
      <TableCell>
        <StatusChip {...courseStatusChip(status, t(`courseStatus.${status}`))} />
      </TableCell>
      <TableCell>
        {enrollment.classification ? t(`classifications.${enrollment.classification}`) : ''}
      </TableCell>
      <TableCell>
        <MeetingTimes meetings={meetings} />
      </TableCell>
      <TableCell align="right">{enrollment.credit_hours}</TableCell>
      <TableCell>
        <AttendanceCell attendance={enrollment.attendance} />
      </TableCell>
      {showActions && (
        <TableCell align="right">
          {!isWithdrawn && canDrop && (
            <Button
              size="small"
              color="warning"
              disabled={disabled}
              onClick={onDrop}
              aria-label={t('schedule.actions.dropTitle', { course: course?.code ?? '' })}
            >
              {t('schedule.actions.drop')}
            </Button>
          )}
          {!isWithdrawn && canWithdraw && (
            <Button
              size="small"
              color="error"
              disabled={disabled}
              onClick={onWithdraw}
              aria-label={t('schedule.actions.withdrawTitle', { course: course?.code ?? '' })}
            >
              {t('schedule.actions.withdraw')}
            </Button>
          )}
        </TableCell>
      )}
    </TableRow>
  )
}

interface ConfirmActionDialogProps {
  pending: PendingAction | null
  acting: boolean
  error: unknown
  onConfirm: () => void
  onCancel: () => void
}

/** The confirmation a free drop or a withdrawal requires before it commits (irreversible). */
function ConfirmActionDialog({
  pending,
  acting,
  error,
  onConfirm,
  onCancel,
}: ConfirmActionDialogProps) {
  const { t } = useTranslation('enrollment')
  const code = pending?.enrollment.course?.code ?? ''
  const isDrop = pending?.kind === 'drop'
  const titleKey = isDrop ? 'schedule.actions.dropTitle' : 'schedule.actions.withdrawTitle'
  const bodyKey = isDrop ? 'schedule.actions.dropBody' : 'schedule.actions.withdrawBody'

  return (
    <Dialog open={pending !== null} onClose={onCancel} aria-labelledby="schedule-confirm-title">
      <DialogTitle id="schedule-confirm-title">{t(titleKey, { course: code })}</DialogTitle>
      <DialogContent>
        <DialogContentText>{t(bodyKey, { course: code })}</DialogContentText>
        {error != null && (
          <Alert severity="error" sx={{ mt: 2 }}>
            {apiErrorMessage(error) ?? t('schedule.actions.error')}
          </Alert>
        )}
      </DialogContent>
      <DialogActions>
        <Button onClick={onCancel} disabled={acting}>
          {t('schedule.actions.cancel')}
        </Button>
        <Button
          onClick={onConfirm}
          disabled={acting}
          variant="contained"
          color={isDrop ? 'warning' : 'error'}
        >
          {t('schedule.actions.confirm')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
