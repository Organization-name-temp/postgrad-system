import { useMemo, useState } from 'react'
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  MenuItem,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material'
import { Link as RouterLink, useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  useASingleRequestForLineItemReview,
  useRecordTheAdvisorsLineItemDecision,
} from '../../../shared/api/generated/endpoints'
import type {
  ASingleRequestForLineItemReview200DataRequestLinesItem as ReviewLine,
  ASingleRequestForLineItemReview200DataStudent as ReviewStudent,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { MeetingTimes } from '../MeetingTimes'

type LineChoice = 'approved' | 'rejected'

/**
 * The Advisor's line-item review of one registration request (features/enrollment,
 * ticket 06). Shows the Student's context — Program, degree, enrollment term, current load —
 * beside every requested line (classification, credit hours, meeting times, derived-Retake
 * label). The Advisor approves or rejects each line and adds an optional note, then records
 * the decision: approving mints the Enrollments server-side and returns to the queue. The
 * server re-validates and stays authoritative; a rejected re-validation surfaces here.
 * Gated on `registration.approve` (route + API).
 */
export function RegistrationReviewPage() {
  const { t } = useTranslation('enrollment')
  const navigate = useNavigate()
  const { requestId } = useParams()
  const registrationId = Number(requestId)

  const { data, isPending, isError } = useASingleRequestForLineItemReview(registrationId)
  const decision = useRecordTheAdvisorsLineItemDecision()

  const request = data?.data?.request
  const student = data?.data?.student
  const lines = useMemo(() => request?.lines ?? [], [request])
  const alreadyDecided = Boolean(request && request.status && request.status !== 'Pending')

  // Line choices default to approve; the advisor trims by switching a line to reject.
  const [choices, setChoices] = useState<Record<number, LineChoice>>({})
  const [note, setNote] = useState('')

  const choiceFor = (lineId: number): LineChoice => choices[lineId] ?? 'approved'
  const approvedCount = lines.filter(
    (line) => line.id != null && choiceFor(line.id) === 'approved',
  ).length

  const submit = async () => {
    await decision.mutateAsync({
      registrationId,
      data: {
        note: note.trim() === '' ? null : note.trim(),
        lines: lines
          .filter((line) => line.id != null)
          .map((line) => ({ id: line.id as number, decision: choiceFor(line.id as number) })),
      },
    })
    navigate('/advising')
  }

  return (
    <>
      <Button component={RouterLink} to="/advising" size="small" sx={{ mb: 2 }}>
        {t('review.back')}
      </Button>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('review.title')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('review.loading')} sx={{ mt: 2 }} />}
      {isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('review.loadError')}
        </Alert>
      )}

      {!isPending && !isError && request && (
        <Stack spacing={3}>
          {student && <StudentContext student={student} />}

          {alreadyDecided && <Alert severity="info">{t('review.alreadyDecided')}</Alert>}

          <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 2, sm: 3 } }}>
            <Typography variant="h6" component="h2" sx={{ mb: 2 }}>
              {t('review.linesHeading')}
            </Typography>
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>{t('review.columns.course')}</TableCell>
                    <TableCell>{t('review.columns.classification')}</TableCell>
                    <TableCell>{t('review.columns.mode')}</TableCell>
                    <TableCell>{t('review.columns.meetings')}</TableCell>
                    <TableCell align="right">{t('review.columns.creditHours')}</TableCell>
                    {!alreadyDecided && <TableCell>{t('review.columns.decision')}</TableCell>}
                  </TableRow>
                </TableHead>
                <TableBody>
                  {lines.map((line) => (
                    <LineRow
                      key={line.id}
                      line={line}
                      choice={line.id != null ? choiceFor(line.id) : 'approved'}
                      readOnly={alreadyDecided}
                      onChange={(choice) =>
                        line.id != null &&
                        setChoices((prev) => ({ ...prev, [line.id as number]: choice }))
                      }
                    />
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </Paper>

          {!alreadyDecided && (
            <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 2, sm: 3 } }}>
              <Typography variant="h6" component="h2" sx={{ mb: 2 }}>
                {t('review.decisionHeading')}
              </Typography>
              <TextField
                label={t('review.note')}
                placeholder={t('review.notePlaceholder')}
                value={note}
                onChange={(event) => setNote(event.target.value)}
                multiline
                minRows={2}
                fullWidth
                sx={{ mb: 2 }}
              />
              <Stack
                direction={{ xs: 'column', sm: 'row' }}
                spacing={2}
                sx={{ alignItems: { sm: 'center' }, justifyContent: 'space-between' }}
              >
                <Typography variant="body2" color="text.secondary">
                  {t('review.approvedSummary', { count: approvedCount })}
                </Typography>
                <Button variant="contained" onClick={submit} disabled={decision.isPending}>
                  {decision.isPending ? t('review.submitting') : t('review.submit')}
                </Button>
              </Stack>
              {decision.isError && (
                <Alert severity="error" sx={{ mt: 2 }}>
                  {apiErrorMessage(decision.error) ?? t('review.submitError')}
                </Alert>
              )}
            </Paper>
          )}
        </Stack>
      )}
    </>
  )
}

/** The student-context panel beside the lines. */
function StudentContext({ student }: { student: ReviewStudent }) {
  const { t, i18n } = useTranslation('enrollment')
  const program = student?.program
  const programName = program ? pickLocaleName(program, i18n.language, program.code ?? '') : '—'

  return (
    <Paper
      component="section"
      aria-label={t('review.studentHeading')}
      variant="outlined"
      sx={{ borderRadius: 3, p: { xs: 2, sm: 3 } }}
    >
      <Typography variant="h6" component="h2" gutterBottom>
        {student?.name}{' '}
        <Typography component="span" variant="body2" color="text.secondary">
          {student?.university_id}
        </Typography>
      </Typography>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={{ xs: 0.5, sm: 4 }}>
        <Field label={t('review.program')} value={programName} />
        <Field label={t('review.degree')} value={student?.degree_level ?? '—'} />
        <Field label={t('review.enrollmentTerm')} value={student?.enrollment_term ?? '—'} />
        <Field
          label={t('review.currentLoad')}
          value={t('review.currentLoadValue', { count: student?.current_load ?? 0 })}
        />
      </Stack>
      {student?.student_id && (
        <Button
          component={RouterLink}
          to={`/grading/students/${student.student_id}`}
          size="small"
          variant="outlined"
          sx={{ mt: 2 }}
        >
          {t('grading:oversight.title')}
        </Button>
      )}
    </Paper>
  )
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <Box>
      <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
        {label}
      </Typography>
      <Typography variant="body2">{value}</Typography>
    </Box>
  )
}

function LineRow({
  line,
  choice,
  readOnly,
  onChange,
}: {
  line: ReviewLine
  choice: LineChoice
  readOnly: boolean
  onChange: (choice: LineChoice) => void
}) {
  const { t, i18n } = useTranslation('enrollment')
  const course = line.course
  const name = course ? pickLocaleName(course, i18n.language) : ''
  const meetings = line.meetings ?? []

  return (
    <TableRow>
      <TableCell>
        <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
          {course?.code}
        </Typography>{' '}
        {name}
        {line.derived_retake && (
          <Chip
            size="small"
            color="warning"
            label={t('review.retakeLabel')}
            sx={{ marginInlineStart: 1 }}
          />
        )}
      </TableCell>
      <TableCell>
        {line.classification ? t(`classifications.${line.classification}`) : ''}
      </TableCell>
      <TableCell>{line.mode ? t(`request.mode.${line.mode}`) : ''}</TableCell>
      <TableCell>
        <MeetingTimes meetings={meetings} />
      </TableCell>
      <TableCell align="right">{line.total_ch}</TableCell>
      {!readOnly && (
        <TableCell>
          <TextField
            select
            size="small"
            value={choice}
            onChange={(event) => onChange(event.target.value as LineChoice)}
            // The accessible name must land on the select's combobox, not the TextField root
            // (a bare `aria-label` on the root FormControl is a prohibited-attr + leaves the
            // combobox unnamed — WCAG 4.1.2). `slotProps.htmlInput` forwards it to the input.
            slotProps={{
              htmlInput: {
                'aria-label': t(
                  choice === 'approved' ? 'review.approveLine' : 'review.rejectLine',
                  {
                    course: course?.code ?? '',
                  },
                ),
              },
            }}
            sx={{ minWidth: 120 }}
          >
            <MenuItem value="approved">{t('review.lineDecision.approved')}</MenuItem>
            <MenuItem value="rejected">{t('review.lineDecision.rejected')}</MenuItem>
          </TextField>
        </TableCell>
      )}
    </TableRow>
  )
}
