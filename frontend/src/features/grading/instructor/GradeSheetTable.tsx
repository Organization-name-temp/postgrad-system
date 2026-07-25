import {
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
import type { SxProps, Theme } from '@mui/material/styles'
import { useTranslation } from 'react-i18next'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { gradeEmphasisFor, gradeLineChip } from '../gradeSheetChip'
import type { GradeEmphasis } from '../gradeSheetChip'
import { isInvalidMarkInput, letterGradeFor, parseMark } from '../letterGrade'

/** One line of the Sheet — a gradable Enrollment and the Grade recorded for it, if any. */
export interface GradeSheetLine {
  enrollment_id: number
  course_status: string
  credit_hours: number
  student: { name?: string; university_id?: string }
  grade: { mark?: number; letter_grade?: string; gpa_points?: number } | null
}

interface GradeSheetTableProps {
  lines: GradeSheetLine[]
  /** What is currently in each row's input, keyed by enrollment id — the page owns it. */
  drafts: Record<number, string>
  /** The row currently being saved, if any — its input is locked while the write is in flight. */
  savingEnrollmentId: number | null
  /** Per-row save failure (the server's message), keyed by enrollment id. */
  errors: Record<number, string>
  onChange: (enrollmentId: number, value: string) => void
  onCommit: (enrollmentId: number) => void
}

/**
 * The Grade Sheet editor (ticket 01): one row per gradable Enrollment, with a single numeric
 * input for the final percentage and a **live letter preview** beside it, so a mistyped figure
 * is obvious before anything is committed (ADR-0014's client mirror of the ADR-0021 scale).
 *
 * Built on MUI `Table` with one labelled input per row, deliberately — `@mui/x-data-grid`
 * stays deferred to Phase 7: rosters are tens of rows, so virtualization and column tooling
 * buy nothing, and a table of labelled inputs is far easier through the axe gate than a grid's
 * cell-editing model.
 *
 * Each mark commits on blur, one line at a time (the server saves per line, never as a batch),
 * so a long roster survives a closed tab. Presentational and controlled — the page owns the
 * drafts and the mutation — so it is unit-testable in isolation.
 */
export function GradeSheetTable({
  lines,
  drafts,
  savingEnrollmentId,
  errors,
  onChange,
  onCommit,
}: GradeSheetTableProps) {
  const { t } = useTranslation('grading')

  return (
    <TableContainer sx={{ overflowX: 'auto' }}>
      <Table size="small" aria-label={t('sheet.tableLabel')}>
        <TableHead>
          <TableRow>
            <TableCell>{t('sheet.columns.student')}</TableCell>
            <TableCell align="right">{t('sheet.columns.creditHours')}</TableCell>
            <TableCell>{t('sheet.columns.mark')}</TableCell>
            <TableCell>{t('sheet.columns.letter')}</TableCell>
            <TableCell>{t('sheet.columns.state')}</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {lines.map((line) => {
            const studentName = line.student.name ?? ''
            const draft = drafts[line.enrollment_id] ?? ''
            const invalid = isInvalidMarkInput(draft)
            const saveError = errors[line.enrollment_id]
            // The letter column answers "what would this mark be worth", and falls back to
            // "what is on the record". While a valid mark is typed the preview follows the
            // input; while it is blank or invalid nothing has been saved, so the column shows
            // the Grade actually recorded — which the row's state chip agrees with. Clearing a
            // field is not a delete (ticket 01 has no way to unrecord a Grade), so the letter
            // and the chip both keep saying so rather than pretending the line went blank.
            const preview = letterGradeFor(parseMark(draft))
            const recorded = line.grade != null

            return (
              <TableRow key={line.enrollment_id}>
                <TableCell>
                  <Stack spacing={0.5} sx={{ alignItems: 'flex-start' }}>
                    <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
                      {studentName}
                    </Typography>
                    {line.student.university_id && (
                      <Typography variant="caption" component="span" color="text.secondary">
                        {line.student.university_id}
                      </Typography>
                    )}
                  </Stack>
                </TableCell>
                <TableCell align="right">{line.credit_hours}</TableCell>
                <TableCell>
                  <TextField
                    size="small"
                    value={draft}
                    disabled={savingEnrollmentId === line.enrollment_id}
                    error={invalid || saveError !== undefined}
                    helperText={invalid ? t('sheet.markInvalid') : saveError}
                    onChange={(event) => onChange(line.enrollment_id, event.target.value)}
                    onBlur={() => onCommit(line.enrollment_id)}
                    slotProps={{
                      htmlInput: {
                        // Every input is individually named with the student it belongs to —
                        // the column header alone would leave a screen-reader user with N
                        // identically named fields. No visible label: the header already says
                        // "Mark", and repeating it per row would be noise on screen.
                        'aria-label': t('sheet.markFor', { student: studentName }),
                        inputMode: 'decimal',
                        // A native numeric range so the browser and assistive tech know the
                        // bounds too, mirroring the server's between:0,100.
                        type: 'number',
                        min: 0,
                        max: 100,
                        step: 0.01,
                      },
                    }}
                    sx={{ width: 120 }}
                  />
                </TableCell>
                <TableCell>
                  <LetterPreview
                    letter={preview?.letter ?? line.grade?.letter_grade}
                    points={preview?.points ?? line.grade?.gpa_points}
                  />
                </TableCell>
                <TableCell>
                  <StatusChip
                    {...gradeLineChip(
                      recorded,
                      t(recorded ? 'sheet.lineState.recorded' : 'sheet.lineState.blank'),
                    )}
                  />
                </TableCell>
              </TableRow>
            )
          })}
        </TableBody>
      </Table>
    </TableContainer>
  )
}

/**
 * Per design-system.md §10 ("Grade display"), the emphasis each grade group carries. `.main`
 * is a fill colour, so a *label* takes the accessible shade — `.dark` in light, `.light` in
 * dark — the same rule StatusChip and the button overrides follow.
 */
const EMPHASIS_SX: Record<GradeEmphasis, SxProps<Theme>> = {
  earned: { color: 'text.primary' },
  subEarn: [
    (theme: Theme) => ({ color: theme.vars.palette.warning.dark }),
    (theme: Theme) => theme.applyStyles('dark', { color: theme.vars.palette.warning.light }),
  ],
  fail: [
    (theme: Theme) => ({ color: theme.vars.palette.error.dark }),
    (theme: Theme) => theme.applyStyles('dark', { color: theme.vars.palette.error.light }),
  ],
}

/**
 * The letter and its GPA points for one row — a dash while the line has neither a typed mark
 * nor a recorded one, so a blank line never reads as an F. The letter carries its §10 grade
 * colour: ordinary text at or above C+, the sub-earn warning below it, error for an F. Colour
 * is emphasis only — the letter is always shown, so it is never the sole signal (WCAG 1.4.1).
 */
function LetterPreview({ letter, points }: { letter?: string; points?: number }) {
  const { t } = useTranslation('grading')

  if (letter === undefined) {
    return (
      <Typography component="span" variant="body2" color="text.secondary">
        {t('sheet.noLetter')}
      </Typography>
    )
  }

  return (
    <Stack direction="row" spacing={1} sx={{ alignItems: 'baseline' }}>
      <Typography
        component="span"
        variant="body2"
        sx={[{ fontWeight: 600 }, EMPHASIS_SX[gradeEmphasisFor(points)]].flat()}
      >
        {letter}
      </Typography>
      {points !== undefined && (
        <Typography component="span" variant="caption" color="text.secondary">
          {t('sheet.points', { points: points.toFixed(2) })}
        </Typography>
      )}
    </Stack>
  )
}
