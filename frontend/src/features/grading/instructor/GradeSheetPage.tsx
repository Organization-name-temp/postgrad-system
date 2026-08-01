import { useState } from 'react'
import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import {
  Alert,
  Button,
  Checkbox,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  Paper,
  Stack,
  Typography,
} from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { Link as RouterLink, useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  getMyOfferingsToGradeQueryKey,
  getOpenAnOfferingsGradeSheetQueryKey as sheetQueryKey,
  useOpenACorrectionRoundAgainstAFinalizedSheet,
  useOpenAnOfferingsGradeSheet,
  useSaveOneGradeSheetLine,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage, apiFieldErrors } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { readGradeHistory } from '../gradeHistory'
import { gradeSheetChip } from '../gradeSheetChip'
import { coerceNullableObject } from '../incompleteLine'
import { GradeSheetTable } from './GradeSheetTable'
import type { GradeSheetLine } from './GradeSheetTable'
import { useGradeSheetEditor } from './useGradeSheetEditor'

/**
 * One Offering's Grade Sheet (features/grading, ticket 01): the assigned Instructor's mark
 * entry. Opening the page opens the Sheet — the Initial round is created lazily server-side on
 * that first read — and each mark is committed **on blur, one line at a time**, matching the
 * server's per-line save so a long roster is never lost to a closed tab.
 *
 * The page owns the drafts and the mutation (through {@see useGradeSheetEditor}, shared with
 * {@see IncompleteResolutionPage}); the table is presentational. The server stays authoritative:
 * it re-derives the letter from the mark and returns the frozen Grade, so the client mirror is
 * only ever a preview.
 */
export function GradeSheetPage() {
  const { t, i18n } = useTranslation('grading')
  const { offeringId: offeringIdParam } = useParams<{ offeringId: string }>()
  const offeringId = Number(offeringIdParam)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data, isPending, isError, error } = useOpenAnOfferingsGradeSheet(offeringId)
  // A separate mutation instance from the shared hook's — declaring an Incomplete is this
  // page's own act, never shared with IncompleteResolutionPage (a resolution round only ever
  // enters a mark).
  const saveLine = useSaveOneGradeSheetLine()
  const createCorrection = useOpenACorrectionRoundAgainstAFinalizedSheet()

  const loaded = data?.data
  const course = loaded?.course
  const sheet = loaded?.sheet
  const sheetId = sheet?.id
  const sheetStatus = sheet?.status ?? 'Draft'
  const [correctionOpen, setCorrectionOpen] = useState(false)
  const [selectedForCorrection, setSelectedForCorrection] = useState<number[]>([])
  const [correctionError, setCorrectionError] = useState<string | null>(null)

  const lines: GradeSheetLine[] = (loaded?.lines ?? []).map((line) => ({
    enrollment_id: line.enrollment_id ?? 0,
    course_status: line.course_status ?? 'Regular',
    credit_hours: line.credit_hours ?? 0,
    student: { name: line.student?.name, university_id: line.student?.university_id },
    grade: line.grade ?? null,
    grade_history: readGradeHistory(line),
    incomplete: coerceNullableObject<GradeSheetLine['incomplete']>(line.incomplete),
  }))

  const {
    seeded,
    errors,
    setErrors,
    savingEnrollmentId,
    setSavingEnrollmentId,
    signError,
    blankCount,
    setDraft,
    commit,
    handleSign,
    signPending,
  } = useGradeSheetEditor({
    sheetId,
    lines,
    // A declared Incomplete is decided, not blank (ticket 05) — only a genuinely ungraded
    // line, with neither a mark nor an Incomplete, blocks signing.
    isLineBlank: (line) => line.grade === null && line.incomplete == null,
    onSaved: () => queryClient.invalidateQueries({ queryKey: sheetQueryKey(offeringId) }),
    onSigned: async () => {
      await queryClient.invalidateQueries({ queryKey: sheetQueryKey(offeringId) })
      await queryClient.invalidateQueries({ queryKey: getMyOfferingsToGradeQueryKey() })
    },
    saveErrorFallback: t('sheet.saveError'),
    signErrorFallback: t('sheet.signError'),
  })

  // The Sheet may be signed from Draft or Returned (ADR-0023); Submitted and Finalized are
  // read-only.
  const canSign = sheetStatus === 'Draft' || sheetStatus === 'Returned'

  const toggleCorrectionLine = (enrollmentId: number, checked: boolean) => {
    setSelectedForCorrection((current) =>
      checked ? [...current, enrollmentId] : current.filter((id) => id !== enrollmentId),
    )
  }

  const openCorrectionDialog = () => {
    setCorrectionError(null)
    setSelectedForCorrection([])
    setCorrectionOpen(true)
  }

  const startCorrection = async () => {
    if (sheetId === undefined) return
    if (selectedForCorrection.length === 0) {
      setCorrectionError(t('correction.pickAtLeastOne'))
      return
    }

    setCorrectionError(null)
    try {
      const response = await createCorrection.mutateAsync({
        gradeSheetId: sheetId,
        data: { enrollment_ids: selectedForCorrection },
      })
      const correctionSheetId = response.data?.sheet?.id
      if (typeof correctionSheetId === 'number') {
        navigate(`/grading/corrections/${correctionSheetId}`)
      }
    } catch (startError) {
      setCorrectionError(apiErrorMessage(startError) ?? t('correction.startError'))
    }
  }

  /**
   * Declare (or correct) an Incomplete on one line (ticket 05, SRS §4.7.3.1) — one explicit
   * Save, not a commit-on-blur, since a percentage or a reason alone means nothing. The client
   * checks only that both fields are present; the 70% threshold is enforced server-side, and
   * its 422 surfaces here exactly like a mark's. Shares the hook's `errors`/`savingEnrollmentId`
   * state — a row is never "saving" on two fronts at once.
   */
  const declareIncomplete = async (enrollmentId: number, percentage: string, reason: string) => {
    if (sheetId === undefined) return

    const trimmedReason = reason.trim()
    const parsedPercentage = Number(percentage)
    if (percentage.trim() === '' || !Number.isFinite(parsedPercentage)) {
      setErrors((prev) => ({ ...prev, [enrollmentId]: t('sheet.incomplete.percentageInvalid') }))
      return
    }
    if (trimmedReason === '') {
      setErrors((prev) => ({ ...prev, [enrollmentId]: t('sheet.incomplete.reasonRequired') }))
      return
    }

    setSavingEnrollmentId(enrollmentId)
    try {
      await saveLine.mutateAsync({
        gradeSheetId: sheetId,
        enrollmentId,
        data: { completion_percentage: parsedPercentage, reason: trimmedReason },
      })
      await queryClient.invalidateQueries({ queryKey: sheetQueryKey(offeringId) })
      setErrors((prev) => {
        if (prev[enrollmentId] === undefined) return prev
        const next = { ...prev }
        delete next[enrollmentId]
        return next
      })
    } catch (declareError) {
      const fieldErrors = apiFieldErrors(declareError)
      const message =
        fieldErrors.completion_percentage?.[0] ??
        fieldErrors.reason?.[0] ??
        apiErrorMessage(declareError) ??
        t('sheet.incomplete.saveError')
      setErrors((prev) => ({ ...prev, [enrollmentId]: message }))
    } finally {
      setSavingEnrollmentId(null)
    }
  }

  return (
    <>
      <Button component={RouterLink} to="/grading" startIcon={<ArrowBackIcon />} sx={{ mb: 2 }}>
        {t('sheet.back')}
      </Button>

      <Stack direction="row" spacing={2} sx={{ mb: 1, alignItems: 'center', flexWrap: 'wrap' }}>
        <Typography variant="h4" component="h1">
          {t('sheet.title')}
        </Typography>
        {sheet && <StatusChip {...gradeSheetChip(sheetStatus, t(`sheet.status.${sheetStatus}`))} />}
      </Stack>

      {course && (
        <Typography color="text.secondary" sx={{ mb: 3 }}>
          {t('sheet.subtitle', {
            course: `${course.code ?? ''} ${pickLocaleName(course, i18n.language)}`.trim(),
          })}
        </Typography>
      )}

      {isPending && <CircularProgress aria-label={t('sheet.loading')} />}
      {isError && <Alert severity="error">{apiErrorMessage(error) ?? t('sheet.loadError')}</Alert>}

      {sheet && sheetStatus === 'Returned' && sheet.review_note && (
        <Alert severity="warning" sx={{ mb: 2 }}>
          {t('sheet.returnedNote', { note: sheet.review_note })}
        </Alert>
      )}

      {sheet && (
        <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 1.5, sm: 2 } }}>
          {lines.length === 0 ? (
            <Typography color="text.secondary" sx={{ p: 1 }}>
              {t('sheet.emptyRoster')}
            </Typography>
          ) : (
            <>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                {t('sheet.help')}
              </Typography>
              <GradeSheetTable
                lines={lines}
                drafts={seeded}
                savingEnrollmentId={savingEnrollmentId}
                errors={errors}
                onChange={setDraft}
                onCommit={commit}
                allowIncomplete
                onDeclareIncomplete={declareIncomplete}
              />
            </>
          )}

          {canSign && (
            <Stack spacing={1} sx={{ mt: 3, alignItems: 'flex-start' }}>
              {signError && <Alert severity="error">{signError}</Alert>}
              <Typography variant="body2" color="text.secondary">
                {blankCount > 0
                  ? t('sheet.blankCount', { count: blankCount })
                  : t('sheet.allGraded')}
              </Typography>
              <Button
                variant="contained"
                onClick={handleSign}
                disabled={blankCount > 0 || signPending}
              >
                {t('sheet.sign')}
              </Button>
            </Stack>
          )}

          {sheetStatus === 'Finalized' && lines.length > 0 && (
            <Stack spacing={1} sx={{ mt: 3, alignItems: 'flex-start' }}>
              <Typography variant="body2" color="text.secondary">
                {t('correction.startHelp')}
              </Typography>
              <Button variant="outlined" onClick={openCorrectionDialog}>
                {t('correction.start')}
              </Button>
            </Stack>
          )}
        </Paper>
      )}

      <Dialog
        open={correctionOpen}
        onClose={() => setCorrectionOpen(false)}
        fullWidth
        maxWidth="sm"
      >
        <DialogTitle>{t('correction.dialogTitle')}</DialogTitle>
        <DialogContent dividers>
          <Stack spacing={1.5}>
            <Typography variant="body2" color="text.secondary">
              {t('correction.dialogHelp')}
            </Typography>
            {lines.map((line) => (
              <FormControlLabel
                key={line.enrollment_id}
                control={
                  <Checkbox
                    checked={selectedForCorrection.includes(line.enrollment_id)}
                    onChange={(event) =>
                      toggleCorrectionLine(line.enrollment_id, event.target.checked)
                    }
                  />
                }
                label={`${line.student.name ?? ''} (${line.student.university_id ?? ''})`}
              />
            ))}
            {correctionError && <Alert severity="error">{correctionError}</Alert>}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setCorrectionOpen(false)}>{t('correction.cancel')}</Button>
          <Button
            variant="contained"
            onClick={startCorrection}
            disabled={createCorrection.isPending}
          >
            {t('correction.start')}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  )
}
