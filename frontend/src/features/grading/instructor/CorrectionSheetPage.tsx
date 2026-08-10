import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import { Alert, Button, CircularProgress, Paper, Stack, Typography } from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { Link as RouterLink, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  getOpenAnOfferingsGradeSheetQueryKey as offeringSheetQueryKey,
  getOpenOneCorrectionRoundQueryKey as correctionQueryKey,
  useOpenOneCorrectionRound,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { readGradeHistory } from '../gradeHistory'
import { coerceNullableObject } from '../incompleteLine'
import { gradeSheetChip } from '../gradeSheetChip'
import { GradeSheetTable } from './GradeSheetTable'
import type { GradeSheetLine } from './GradeSheetTable'
import { useGradeSheetEditor } from './useGradeSheetEditor'

/**
 * One Correction round (ticket 06): a fresh Sheet opened against a finalized round, containing
 * only the lines the Instructor chose to change. It reuses the same editor and sign flow as the
 * Initial round, but each row also carries the earlier finalized grade(s) as context so the
 * correction is explicit rather than memory-based.
 */
export function CorrectionSheetPage() {
  const { t, i18n } = useTranslation('grading')
  const { sheetId: sheetIdParam } = useParams<{ sheetId: string }>()
  const correctionSheetId = Number(sheetIdParam)
  const queryClient = useQueryClient()

  const { data, isPending, isError, error } = useOpenOneCorrectionRound(correctionSheetId)

  const loaded = data?.data
  const course = loaded?.course
  const sheet = loaded?.sheet
  const sheetId = sheet?.id
  const sheetStatus = sheet?.status ?? 'Draft'
  const offeringId = sheet?.course_offering_id ?? 0

  const lines: GradeSheetLine[] = (loaded?.lines ?? []).map((line) => ({
    enrollment_id: line.enrollment_id ?? 0,
    course_status: line.course_status ?? 'Regular',
    credit_hours: line.credit_hours ?? 0,
    student: { name: line.student?.name, university_id: line.student?.university_id },
    grade: coerceNullableObject<GradeSheetLine['grade']>(line.grade),
    grade_history: readGradeHistory(line),
    incomplete: coerceNullableObject<GradeSheetLine['incomplete']>(line.incomplete),
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
    isLineBlank: (line) => line.grade === null,
    onSaved: () =>
      queryClient.invalidateQueries({ queryKey: correctionQueryKey(correctionSheetId) }),
    onSigned: async () => {
      await queryClient.invalidateQueries({ queryKey: correctionQueryKey(correctionSheetId) })
      await queryClient.invalidateQueries({ queryKey: offeringSheetQueryKey(offeringId) })
    },
    saveErrorFallback: t('sheet.saveError'),
    signErrorFallback: t('sheet.signError'),
  })

  const canSign = sheetStatus === 'Draft' || sheetStatus === 'Returned'

  return (
    <>
      <Button
        component={RouterLink}
        to={offeringId > 0 ? `/grading/offerings/${offeringId}` : '/grading'}
        startIcon={<ArrowBackIcon />}
        sx={{ mb: 2 }}
      >
        {t('correction.back')}
      </Button>

      <Stack direction="row" spacing={2} sx={{ mb: 1, alignItems: 'center', flexWrap: 'wrap' }}>
        <Typography variant="h4" component="h1">
          {t('correction.title')}
        </Typography>
        {sheet && <StatusChip {...gradeSheetChip(sheetStatus, t(`sheet.status.${sheetStatus}`))} />}
      </Stack>

      {course && (
        <Typography color="text.secondary" sx={{ mb: 3 }}>
          {t('correction.subtitle', {
            course: `${course.code ?? ''} ${pickLocaleName(course, i18n.language)}`.trim(),
          })}
        </Typography>
      )}

      {isPending && <CircularProgress aria-label={t('correction.loading')} />}
      {isError && (
        <Alert severity="error">{apiErrorMessage(error) ?? t('correction.loadError')}</Alert>
      )}

      {sheet && lines.length > 0 && (
        <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 1.5, sm: 2 } }}>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            {t('correction.help')}
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
                {t('correction.sign')}
              </Button>
            </Stack>
          )}
        </Paper>
      )}
    </>
  )
}
