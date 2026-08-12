import { Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useMyAcademicRecordCard } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { DashboardCard } from '../../../shared/ui/DashboardCard'
import { tabularNums } from '../../../app/theme/fonts'

/** A GPA figure to two decimals, or the dash — a fresh record is never `0.00`. */
function gpaDisplay(value: number | null | undefined, dash: string): string {
  return typeof value === 'number' ? value.toFixed(2) : dash
}

/**
 * My Academic Record (Dashboard Card 8, ADR-0024): the Student's CGPA and their latest
 * Semester GPA as numbers, with the credit hours earned so far, linking into My Grades.
 * Owned by the grading feature because Grading owns the data (ADR-0020); the app shell only
 * arranges it.
 *
 * A **persona** Card, gated in the registry on the Student role (R02) rather than on
 * `grades.view_own`: an administrator inherits that Permission (ADR-0008) but holds no Student
 * anchor, so gating on the Permission would render a broken Card — the existing convention the
 * persona nav items have followed since Phase 2. The server refuses it for the same reason.
 *
 * Renders bilingually from typed domain values: the Semester arrives as id/term/year/names, so
 * the label is picked here rather than sent pre-rendered.
 */
export function AcademicRecordCard() {
  const { t, i18n } = useTranslation('grading')
  const { data, isPending, isError } = useMyAcademicRecordCard()
  const record = data?.data
  const dash = t('cards.academicRecord.dash')

  const semesterName = record?.latest_semester
    ? pickLocaleName(record.latest_semester, i18n.language)
    : null

  return (
    <DashboardCard
      title={t('cards.academicRecord.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('cards.academicRecord.loadError')}
      action={{ to: '/grading/my-grades', label: t('cards.academicRecord.action') }}
      count={
        <Stack direction="row" spacing={4} sx={{ flexWrap: 'wrap' }}>
          <Figure label={t('cards.academicRecord.cgpa')} value={gpaDisplay(record?.cgpa, dash)} />
          <Figure
            label={t('cards.academicRecord.semesterGpa')}
            value={gpaDisplay(record?.latest_semester_gpa, dash)}
          />
        </Stack>
      }
      rows={
        <Stack spacing={0.5}>
          {semesterName && (
            <Typography variant="body2" color="text.secondary">
              {t('cards.academicRecord.latestSemester', { semester: semesterName })}
            </Typography>
          )}
          <Typography variant="body2" color="text.secondary" sx={tabularNums}>
            {t('cards.academicRecord.earnedCreditHours', {
              count: record?.earned_credit_hours ?? 0,
            })}
          </Typography>
        </Stack>
      }
    />
  )
}

/** One labelled figure — the label above, the number given the weight below it. */
function Figure({ label, value }: { label: string; value: string }) {
  return (
    <Stack spacing={0}>
      <Typography variant="overline" color="text.secondary">
        {label}
      </Typography>
      <Typography variant="h4" component="p" sx={{ ...tabularNums, fontWeight: 700 }}>
        {value}
      </Typography>
    </Stack>
  )
}
