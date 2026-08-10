import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import { Alert, Button, CircularProgress, Paper, Stack, Typography } from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { Link as RouterLink, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  getMyOutstandingIncompletesQueryKey,
  getOpenorResumeTheIncompletesResolutionRoundQueryKey as resolutionQueryKey,
  useOpenorResumeTheIncompletesResolutionRound,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { gradeSheetChip } from '../gradeSheetChip'
import { coerceNullableObject } from '../incompleteLine'
import { GradeSheetTable } from './GradeSheetTable'
import type { GradeSheetLine } from './GradeSheetTable'
import { useGradeSheetEditor } from './useGradeSheetEditor'

/**
 * An Incomplete's resolution round (ticket 05, SRS §4.7.3.2): the same Sheet editor as the
 * Offering's Initial round ({@see GradeSheetTable}, {@see useGradeSheetEditor}), scoped to the
 * one line the outstanding Incomplete named. Entering the outstanding mark, signing and
 * Department-Head finalization all behave exactly as ticket 03 established — this page differs
 * from {@see GradeSheetPage} only in where its Sheet comes from (one Incomplete's resolution
 * round, not an Offering's Initial round) and in never offering to declare a *second* Incomplete
 * on an already-Incomplete line.
 */
export function IncompleteResolutionPage() {
  const { t, i18n } = useTranslation('grading')
  const { incompleteId: incompleteIdParam } = useParams<{ incompleteId: string }>()
  const incompleteId = Number(incompleteIdParam)
  const queryClient = useQueryClient()

  const { data, isPending, isError, error } =
    useOpenorResumeTheIncompletesResolutionRound(incompleteId)

  const loaded = data?.data
  const course = loaded?.course
  const sheet = loaded?.sheet
  const sheetId = sheet?.id
  const sheetStatus = sheet?.status ?? 'Draft'

  const lines: GradeSheetLine[] = (loaded?.lines ?? []).map((line) => ({
    enrollment_id: line.enrollment_id ?? 0,
    course_status: line.course_status ?? 'Incomplete',
    credit_hours: line.credit_hours ?? 0,
    student: { name: line.student?.name, university_id: line.student?.university_id },
    grade: coerceNullableObject<GradeSheetLine['grade']>(line.grade),
    incomplete: line.incomplete ?? null,
  }))

  const {
    seeded,
    errors,
    savingEnrollmentId,
    signError,
    blankCount,
    setDraft,
    commit,
    handleSign,
    signPending,
  } = useGradeSheetEditor({
    sheetId,
    lines,
    // The round's one line is never itself Incomplete-declared (only entered), so blank is
    // simply "no mark yet" — unlike the Initial round, an Incomplete on this line is what
    // brought it here, not a second decision to account for.
    isLineBlank: (line) => line.grade === null,
    onSaved: () => queryClient.invalidateQueries({ queryKey: resolutionQueryKey(incompleteId) }),
    onSigned: async () => {
      await queryClient.invalidateQueries({ queryKey: resolutionQueryKey(incompleteId) })
      await queryClient.invalidateQueries({ queryKey: getMyOutstandingIncompletesQueryKey() })
    },
    saveErrorFallback: t('sheet.saveError'),
    signErrorFallback: t('sheet.signError'),
  })

  const canSign = sheetStatus === 'Draft' || sheetStatus === 'Returned'

  return (
    <>
      <Button
        component={RouterLink}
        to="/grading/incompletes"
        startIcon={<ArrowBackIcon />}
        sx={{ mb: 2 }}
      >
        {t('incompleteResolution.back')}
      </Button>

      <Stack direction="row" spacing={2} sx={{ mb: 1, alignItems: 'center', flexWrap: 'wrap' }}>
        <Typography variant="h4" component="h1">
          {t('incompleteResolution.title')}
        </Typography>
        {sheet && <StatusChip {...gradeSheetChip(sheetStatus, t(`sheet.status.${sheetStatus}`))} />}
      </Stack>

      {course && (
        <Typography color="text.secondary" sx={{ mb: 3 }}>
          {`${course.code ?? ''} ${pickLocaleName(course, i18n.language)}`.trim()}
        </Typography>
      )}

      {isPending && <CircularProgress aria-label={t('incompleteResolution.loading')} />}
      {isError && (
        <Alert severity="error">
          {apiErrorMessage(error) ?? t('incompleteResolution.loadError')}
        </Alert>
      )}

      {sheet && lines.length > 0 && (
        <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 1.5, sm: 2 } }}>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            {t('incompleteResolution.help')}
          </Typography>
          <GradeSheetTable
            lines={lines}
            drafts={seeded}
            savingEnrollmentId={savingEnrollmentId}
            errors={errors}
            onChange={setDraft}
            onCommit={commit}
          />

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
                {t('incompleteResolution.sign')}
              </Button>
            </Stack>
          )}
        </Paper>
      )}
    </>
  )
}
