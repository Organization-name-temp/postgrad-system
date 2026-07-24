import { useState } from 'react'
import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import { Alert, Box, Button, CircularProgress, Paper, Stack, Typography } from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { Link as RouterLink, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  getAnOfferingsAttendanceRegisterSessionsRosterDerivedPercentagesQueryKey as registerQueryKey,
  useAnOfferingsAttendanceRegisterSessionsRosterDerivedPercentages as useRegister,
  useCancelOrReinstateAClassSession,
  useRecordorCorrectASessionsAttendanceRegister,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { markKey, RegisterGrid } from './RegisterGrid'
import type { RegisterRosterRow, RegisterSession } from './RegisterGrid'

/**
 * An Offering's attendance register (features/enrollment, ticket 08): the assigned
 * Instructor's grid of sessions × students. The page owns the staged (unsaved) marks and
 * the mutations — present/absent marks are batched and saved per session (so the server's
 * downward-crossing recompute fires at the right moment), and a session is cancelled or
 * reinstated directly. The server stays authoritative (authorised by assignment); the UI
 * refetches after every write.
 */
export function OfferingRegisterPage() {
  const { t, i18n } = useTranslation('enrollment')
  const { offeringId: offeringIdParam } = useParams<{ offeringId: string }>()
  const offeringId = Number(offeringIdParam)
  const queryClient = useQueryClient()

  const { data, isPending, isError } = useRegister(offeringId)
  const cancel = useCancelOrReinstateAClassSession()
  const mark = useRecordorCorrectASessionsAttendanceRegister()

  // Staged marks keyed by `${sessionId}:${enrollmentId}` → present, committed on Save.
  const [pending, setPending] = useState<Record<string, boolean>>({})

  const register = data?.data
  const offering = register?.offering
  const course = offering?.course
  const semester = offering?.semester

  const sessions: RegisterSession[] = (register?.sessions ?? []).map((s) => ({
    id: s.id ?? 0,
    date: s.date ?? '',
    weekday: s.weekday ?? 0,
    starts_at: s.starts_at,
    ends_at: s.ends_at,
    cancelled: s.cancelled ?? false,
  }))

  const roster: RegisterRosterRow[] = (register?.roster ?? []).map((r) => ({
    enrollment_id: r.enrollment_id ?? 0,
    course_status: r.course_status ?? 'Regular',
    student: { name: r.student?.name, university_id: r.student?.university_id },
    attendance: {
      recorded: r.attendance?.recorded,
      percentage: r.attendance?.percentage,
      at_risk: r.attendance?.at_risk,
    },
    // The marks map has dynamic session-id keys the generated types can't express.
    marks: (r.marks ?? {}) as Record<string, boolean>,
  }))

  const busy = cancel.isPending || mark.isPending
  const unsavedCount = Object.keys(pending).length
  const saveError = mark.error ?? cancel.error

  const refetch = () => queryClient.invalidateQueries({ queryKey: registerQueryKey(offeringId) })

  const onToggleMark = (sessionId: number, enrollmentId: number, present: boolean) =>
    setPending((prev) => ({ ...prev, [markKey(sessionId, enrollmentId)]: present }))

  const onToggleCancel = async (session: RegisterSession) => {
    await cancel.mutateAsync({
      classSessionId: session.id,
      data: { cancelled: !session.cancelled },
    })
    await refetch()
  }

  const save = async () => {
    // Group the staged marks by session, then save session by session in date order — so the
    // server's downward-crossing recompute fires once, at the session where a percentage dips.
    const bySession = new Map<number, { enrollment_id: number; present: boolean }[]>()
    for (const [key, present] of Object.entries(pending)) {
      const [sessionId, enrollmentId] = key.split(':').map(Number)
      const marks = bySession.get(sessionId) ?? []
      marks.push({ enrollment_id: enrollmentId, present })
      bySession.set(sessionId, marks)
    }

    for (const session of sessions) {
      const marks = bySession.get(session.id)
      if (marks) {
        await mark.mutateAsync({ classSessionId: session.id, data: { marks } })
      }
    }

    setPending({})
    await refetch()
  }

  return (
    <>
      <Button
        component={RouterLink}
        to="/offerings/mine"
        startIcon={<ArrowBackIcon />}
        sx={{ mb: 2 }}
      >
        {t('attendance.back')}
      </Button>

      <Typography variant="h4" component="h1" sx={{ mb: 1 }}>
        {t('attendance.title')}
      </Typography>
      {course && semester && (
        <Typography color="text.secondary" sx={{ mb: 3 }}>
          {t('attendance.subtitle', {
            course: `${course.code ?? ''} ${pickLocaleName(course, i18n.language)}`.trim(),
            semester: pickLocaleName(semester, i18n.language),
          })}
        </Typography>
      )}

      {isPending && <CircularProgress aria-label={t('attendance.loading')} />}
      {isError && <Alert severity="error">{t('attendance.loadError')}</Alert>}

      {register && (
        <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 1.5, sm: 2 } }}>
          {roster.length === 0 ? (
            <Typography color="text.secondary" sx={{ p: 1 }}>
              {t('attendance.emptyRoster')}
            </Typography>
          ) : sessions.length === 0 ? (
            <Typography color="text.secondary" sx={{ p: 1 }}>
              {t('attendance.emptySessions')}
            </Typography>
          ) : (
            <>
              <RegisterGrid
                sessions={sessions}
                roster={roster}
                pending={pending}
                busy={busy}
                onToggleMark={onToggleMark}
                onToggleCancel={onToggleCancel}
              />

              {saveError != null && (
                <Alert severity="error" sx={{ mt: 2 }}>
                  {apiErrorMessage(saveError) ?? t('attendance.saveError')}
                </Alert>
              )}

              <Stack
                direction="row"
                spacing={2}
                sx={{ mt: 2, alignItems: 'center', justifyContent: 'flex-end' }}
              >
                {unsavedCount > 0 && (
                  <Typography variant="body2" color="text.secondary">
                    {t('attendance.unsaved', { count: unsavedCount })}
                  </Typography>
                )}
                <Box>
                  <Button variant="contained" onClick={save} disabled={busy || unsavedCount === 0}>
                    {mark.isPending ? t('attendance.saving') : t('attendance.save')}
                  </Button>
                </Box>
              </Stack>
            </>
          )}
        </Paper>
      )}
    </>
  )
}
