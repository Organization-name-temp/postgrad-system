import { useState } from 'react'
import { Alert, Box, CircularProgress, Paper, Stack, Typography } from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getATargetStudentsEligibleCoursesForTheOpenSemesterQueryKey,
  useATargetStudentsEligibleCoursesForTheOpenSemester,
  useComposeAndSubmitARegistrationCartOnAStudentsBehalf,
  useTheStudentsTheComposerMayRegisterOnBehalfOf,
} from '../../../shared/api/generated/endpoints'
import type { TheStudentsTheComposerMayRegisterOnBehalfOf200DataItem as ComposableStudent } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { CartBuilder } from '../registration/CartBuilder'
import type { EligibleCourse } from '../registration/classification'
import { StudentPicker } from '../../../shared/ui/StudentPicker'

/**
 * Register courses on a student's behalf (features/enrollment, ticket 09). Staff pick a
 * student, then build the same cart the student would — reusing {@link CartBuilder} against
 * the target student's eligible list — and submit it. The two composers differ server-side,
 * not here: Student Affairs composing produces a Pending request routed to the student's
 * advisor, while the student's own Advisor composing produces a request born Approved that
 * mints immediately (the composer is the approver). Gated on `courses.register_for_student`
 * (route + API); an Advisor's picker is scoped to their advisees, Student Affairs sees all.
 */
export function ComposeForStudentPage() {
  const { t, i18n } = useTranslation('enrollment')
  const queryClient = useQueryClient()
  const [student, setStudent] = useState<ComposableStudent | null>(null)
  // null = not yet composed; otherwise the server's outcome for the composed request, so the
  // confirmation is honest: an advisor composing for their advisee enrols on the spot
  // (Approved), while SA composing routes a Pending request to the advisor (story 50 vs 53).
  const [outcome, setOutcome] = useState<string | null>(null)

  const studentsQuery = useTheStudentsTheComposerMayRegisterOnBehalfOf()
  const students = studentsQuery.data?.data ?? []

  const studentId = student?.student_id ?? null
  const eligibleQuery = useATargetStudentsEligibleCoursesForTheOpenSemester(studentId ?? 0, {
    query: { enabled: studentId != null },
  })
  const semester = eligibleQuery.data?.data?.semester
  // The target-eligible and self-eligible course items are the same wire shape (identical
  // Scribe example), so Orval generates structurally-identical types under different names;
  // the cast reconciles the two nominal types for the shared CartBuilder/classification code.
  const courses = (eligibleQuery.data?.data?.courses ?? []) as EligibleCourse[]

  const composeMutation = useComposeAndSubmitARegistrationCartOnAStudentsBehalf()

  // Mirror the server's first-semester CH waiver: the target semester is the student's
  // enrollment term (matched by natural key — the display name, ADR-0018).
  const firstSemester = Boolean(
    student?.enrollment_term && semester?.name_en && student.enrollment_term === semester.name_en,
  )

  const onPick = (next: ComposableStudent | null) => {
    setStudent(next)
    setOutcome(null)
  }

  const semesterName = semester ? pickLocaleName(semester, i18n.language) : ''

  return (
    <>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('onBehalf.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
        {t('onBehalf.intro')}
      </Typography>

      {studentsQuery.isPending && <CircularProgress aria-label={t('onBehalf.loadingStudents')} />}
      {studentsQuery.isError && <Alert severity="error">{t('onBehalf.loadStudentsError')}</Alert>}

      {!studentsQuery.isPending && !studentsQuery.isError && (
        <Stack spacing={3}>
          <StudentPicker
            students={students}
            value={student}
            onChange={onPick}
            disabled={composeMutation.isPending}
          />

          {student && (
            <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 2, sm: 3 } }}>
              <Typography variant="h6" component="p">
                {student.name}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {student.university_id}
                {student.program &&
                  ` · ${pickLocaleName(student.program, i18n.language, student.program.code ?? '')}`}
              </Typography>
            </Paper>
          )}

          {student && eligibleQuery.isPending && (
            <CircularProgress aria-label={t('onBehalf.loadingCourses')} />
          )}
          {student && eligibleQuery.isError && (
            <Alert severity="error">{t('onBehalf.loadCoursesError')}</Alert>
          )}

          {student && !eligibleQuery.isPending && !eligibleQuery.isError && !semester && (
            <Alert severity="info">{t('onBehalf.notOpen')}</Alert>
          )}

          {student && outcome && (
            <Alert severity="success">
              {t(outcome === 'Approved' ? 'onBehalf.enrolled' : 'onBehalf.submitted', {
                student: student.name,
              })}
            </Alert>
          )}

          {student && !outcome && semester && (
            <>
              <Typography variant="subtitle1" component="p">
                {t('onBehalf.forSemester', { semester: semesterName })}
              </Typography>
              {courses.length === 0 ? (
                <Typography color="text.secondary">{t('onBehalf.empty')}</Typography>
              ) : (
                <Box>
                  <CartBuilder
                    key={student.student_id}
                    courses={courses}
                    firstSemester={firstSemester}
                    submitLabel={t('onBehalf.submit')}
                    isSubmitting={composeMutation.isPending}
                    submitError={composeMutation.error}
                    submit={async (lines) => {
                      const created = await composeMutation.mutateAsync({
                        studentId: student.student_id as number,
                        data: { lines },
                      })
                      // Re-read the target's eligible list (seats/capacity moved) and record the
                      // server's outcome (Approved when composed by their advisor, else Pending).
                      await queryClient.invalidateQueries({
                        queryKey: getATargetStudentsEligibleCoursesForTheOpenSemesterQueryKey(
                          student.student_id as number,
                        ),
                      })
                      setOutcome(created.data?.status ?? 'Pending')
                    }}
                  />
                </Box>
              )}
            </>
          )}
        </Stack>
      )}
    </>
  )
}
