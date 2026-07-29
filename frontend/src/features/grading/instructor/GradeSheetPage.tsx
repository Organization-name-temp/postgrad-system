import { useState } from 'react'
import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import { Alert, CircularProgress, Paper, Stack, Typography, Button } from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { Link as RouterLink, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  getMyOfferingsToGradeQueryKey,
  getOpenAnOfferingsGradeSheetQueryKey as sheetQueryKey,
  useOpenAnOfferingsGradeSheet,
  useSaveOneGradeSheetLine,
  useSignTheSheet,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage, apiFieldErrors } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { gradeSheetChip } from '../gradeSheetChip'
import { parseMark } from '../letterGrade'
import { GradeSheetTable } from './GradeSheetTable'
import type { GradeSheetLine } from './GradeSheetTable'

/**
 * One Offering's Grade Sheet (features/grading, ticket 01): the assigned Instructor's mark
 * entry. Opening the page opens the Sheet — the Initial round is created lazily server-side on
 * that first read — and each mark is committed **on blur, one line at a time**, matching the
 * server's per-line save so a long roster is never lost to a closed tab.
 *
 * The page owns the drafts and the mutation; the table is presentational. The server stays
 * authoritative: it re-derives the letter from the mark and returns the frozen Grade, so the
 * client mirror is only ever a preview.
 */
export function GradeSheetPage() {
  const { t, i18n } = useTranslation('grading')
  const { offeringId: offeringIdParam } = useParams<{ offeringId: string }>()
  const offeringId = Number(offeringIdParam)
  const queryClient = useQueryClient()

  const { data, isPending, isError, error } = useOpenAnOfferingsGradeSheet(offeringId)
  const saveLine = useSaveOneGradeSheetLine()
  const sign = useSignTheSheet()

  // What is currently typed in each row, keyed by enrollment id. Seeded from the server's
  // recorded marks the first time the Sheet loads, then owned here — so a row the Instructor
  // is mid-edit is never overwritten by a refetch.
  const [drafts, setDrafts] = useState<Record<number, string> | null>(null)
  const [errors, setErrors] = useState<Record<number, string>>({})
  const [savingEnrollmentId, setSavingEnrollmentId] = useState<number | null>(null)
  const [signError, setSignError] = useState<string | null>(null)

  const loaded = data?.data
  const course = loaded?.course
  const sheet = loaded?.sheet
  const sheetId = sheet?.id
  const sheetStatus = sheet?.status ?? 'Draft'

  const lines: GradeSheetLine[] = (loaded?.lines ?? []).map((line) => ({
    enrollment_id: line.enrollment_id ?? 0,
    course_status: line.course_status ?? 'Regular',
    credit_hours: line.credit_hours ?? 0,
    student: { name: line.student?.name, university_id: line.student?.university_id },
    grade: line.grade ?? null,
  }))

  const seeded =
    drafts ??
    Object.fromEntries(
      lines.map((line) => [line.enrollment_id, line.grade?.mark?.toString() ?? '']),
    )

  // The Sheet may be signed from Draft or Returned (ADR-0023); Submitted and Finalized are
  // read-only. Blank lines are recorded Grades still missing, not the mid-typed draft — the
  // same count the server's blank-line guard enforces.
  const canSign = sheetStatus === 'Draft' || sheetStatus === 'Returned'
  const blankCount = lines.filter((line) => line.grade === null).length

  const setDraft = (enrollmentId: number, value: string) => {
    setDrafts({ ...seeded, [enrollmentId]: value })
    // A fresh keystroke clears the row's stale server error — the message described the
    // previous value, not this one.
    setErrors((prev) => {
      if (prev[enrollmentId] === undefined) return prev
      const next = { ...prev }
      delete next[enrollmentId]
      return next
    })
  }

  /**
   * Commit one row on blur. A row is only sent when the typed value is a mark the server would
   * accept and it actually differs from what is recorded — so tabbing through an already-graded
   * roster writes nothing, and a half-typed or cleared field is left alone rather than sent to
   * be rejected. (Clearing a recorded mark is not a delete: ticket 01 has no way to unrecord a
   * Grade, and inventing one here would be scope the Sheet's lifecycle has not settled.)
   */
  const commit = async (enrollmentId: number) => {
    if (sheetId === undefined) return

    const line = lines.find((candidate) => candidate.enrollment_id === enrollmentId)
    const mark = parseMark(seeded[enrollmentId] ?? '')
    if (line === undefined || mark === null || mark === line.grade?.mark) return

    setSavingEnrollmentId(enrollmentId)
    try {
      await saveLine.mutateAsync({ gradeSheetId: sheetId, enrollmentId, data: { mark } })
      await queryClient.invalidateQueries({ queryKey: sheetQueryKey(offeringId) })
    } catch (saveError) {
      // The server's own message, already localised by Accept-Language — the field message
      // when it is a 422 on `mark`, the envelope message otherwise (403 / 404 / 409).
      const message =
        apiFieldErrors(saveError).mark?.[0] ?? apiErrorMessage(saveError) ?? t('sheet.saveError')
      setErrors((prev) => ({ ...prev, [enrollmentId]: message }))
    } finally {
      setSavingEnrollmentId(null)
    }
  }

  /**
   * Sign the Sheet as one act (SRS §4.7.1.4). The blank-line count already rendered below the
   * button is the same guard the server enforces, so this is a defence against a stale client —
   * not the Instructor's first signal that lines remain. A 422 here still surfaces the server's
   * message, in case the roster changed since the page loaded.
   */
  const handleSign = async () => {
    if (sheetId === undefined) return

    setSignError(null)
    try {
      await sign.mutateAsync({ gradeSheetId: sheetId })
      await queryClient.invalidateQueries({ queryKey: sheetQueryKey(offeringId) })
      await queryClient.invalidateQueries({ queryKey: getMyOfferingsToGradeQueryKey() })
    } catch (signingError) {
      setSignError(
        apiFieldErrors(signingError).lines?.[0] ??
          apiErrorMessage(signingError) ??
          t('sheet.signError'),
      )
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
                disabled={blankCount > 0 || sign.isPending}
              >
                {t('sheet.sign')}
              </Button>
            </Stack>
          )}
        </Paper>
      )}
    </>
  )
}
