import {
  Box,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import type {
  CurrentUnofficialTranscript200Data,
  ReviewCoherentOfficialTranscriptPreview200DataSnapshot,
  ImmutableOfficialTranscriptDetail200DataSnapshot,
  PublicBearerVerificationOfAnOfficialCopy200DataSnapshot,
} from '../../shared/api/generated/endpoints'

export type TranscriptDocumentRecord =
  | CurrentUnofficialTranscript200Data
  | ReviewCoherentOfficialTranscriptPreview200DataSnapshot
  | ImmutableOfficialTranscriptDetail200DataSnapshot
  | PublicBearerVerificationOfAnOfficialCopy200DataSnapshot

const metrics = [
  'baseline_required_ch',
  'adjusted_required_ch',
  'attended_ch',
  'earned_ch',
  'transferred_ch',
  'waived_ch',
  'exempted_ch',
  'satisfied_ch',
  'remaining_ch',
] as const

export function TranscriptDocument({
  record,
  language,
  official = false,
}: {
  record: TranscriptDocumentRecord
  language?: 'en' | 'ar'
  official?: boolean
}) {
  const { t, i18n } = useTranslation('records', { lng: language })
  const arabic = (language ?? i18n.language) === 'ar'
  const name = (value: { name_en?: string; name_ar?: string } | undefined) =>
    arabic ? value?.name_ar : value?.name_en
  const program = record.program
  const number = (value: number | null | undefined) => (value == null ? '—' : value.toFixed(2))
  return (
    <Box
      component="article"
      dir={arabic ? 'rtl' : 'ltr'}
      sx={{ bgcolor: 'background.paper', color: 'text.primary', p: 2, '@media print': { p: 0 } }}
    >
      <Box
        component="img"
        src="/name.png"
        alt={t('university')}
        sx={{ maxWidth: '100%', width: (theme) => theme.spacing(40), mb: 2 }}
      />
      <Typography variant="h5" component="h2">
        {t(official ? 'service.official' : 'unofficial')}
      </Typography>
      <Typography variant="h6">{name(record.identity)}</Typography>
      <Typography dir="ltr" sx={{ unicodeBidi: 'isolate' }}>
        {record.university_id}
      </Typography>
      <Typography>
        {t(`identificationTypes.${record.identity?.identification_type ?? 'unresolved'}`)}
      </Typography>
      <Typography dir="ltr" sx={{ unicodeBidi: 'isolate' }}>
        {record.identity?.identification_number}
      </Typography>
      {!record.identity?.ready && <Typography>{t('identityUnresolved')}</Typography>}
      <Typography>
        {name(program)} · {program?.degree_level}
      </Typography>
      <Typography>
        {arabic ? program?.department_name_ar : program?.department_name_en} ·{' '}
        {arabic ? program?.faculty_name_ar : program?.faculty_name_en}
      </Typography>
      <Typography>
        {record.enrollment_term} · {t(`lifecycle.${record.lifecycle_status}`)}
      </Typography>
      <Typography>
        {t('cgpa')}:{' '}
        <Box component="span" dir="ltr">
          {number(record.cgpa)}
        </Box>
      </Typography>
      {(record.semesters ?? []).map((semester) => (
        <Box key={semester.semester?.id} sx={{ mt: 3 }}>
          <Typography variant="h6" component="h3">
            {name(semester.semester)}
          </Typography>
          <Typography>
            {t('semesterGpa')}:{' '}
            <Box component="span" dir="ltr">
              {number(semester.gpa)}
            </Box>
          </Typography>
          <TableContainer>
            <Table size="small" aria-label={name(semester.semester)}>
              <TableHead>
                <TableRow>
                  {['course', 'creditHours', 'classification', 'result', 'contribution'].map(
                    (key) => (
                      <TableCell key={key}>{t(key)}</TableCell>
                    ),
                  )}
                </TableRow>
              </TableHead>
              <TableBody>
                {(semester.courses ?? []).map((course) => (
                  <TableRow key={course.enrollment_id} sx={{ breakInside: 'avoid' }}>
                    <TableCell>
                      <Box component="span" dir="ltr" sx={{ unicodeBidi: 'isolate' }}>
                        {course.course?.code}
                      </Box>{' '}
                      · <Box component="span">{name(course.course)}</Box>
                    </TableCell>
                    <TableCell>{course.credit_hours}</TableCell>
                    <TableCell>{t(`classifications.${course.classification}`)}</TableCell>
                    <TableCell>
                      {course.result === 'InProgress' ? (
                        t('inProgress')
                      ) : (
                        <Box component="span" dir="ltr">
                          {course.result}
                        </Box>
                      )}
                    </TableCell>
                    <TableCell>
                      {course.contributes_to_cgpa ? t('contributes') : t('doesNotContribute')}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Box>
      ))}
      <Typography variant="h6" component="h3" sx={{ mt: 3 }}>
        {t('creditDecisions')}
      </Typography>
      {(record.credit_decisions ?? []).map((decision, index) => (
        <Box key={index} sx={{ my: 1, breakInside: 'avoid' }}>
          <Typography>
            {t(`decisions.${decision.type}`)} ·{' '}
            <Box component="span" dir="ltr">
              {decision.target_course_code}
            </Box>{' '}
            · {decision.credit_hours} {t('creditHours')} · {decision.approved_at}
          </Typography>
          {decision.type === 'Transfer' && (
            <Typography>
              {decision.source_institution} · {decision.source_course_code} ·{' '}
              {decision.source_course_name} · {decision.original_grade} ·{' '}
              {decision.normalized_grade}
            </Typography>
          )}
          {decision.type === 'Waiver' && (
            <Typography>
              {decision.prior_course_code} · {decision.prior_grade}
            </Typography>
          )}
          {decision.type === 'Exemption' && (
            <Typography>
              {(decision.reason_codes ?? [])
                .map((reason) => t(`reasons.${reason}`, { defaultValue: reason }))
                .join(' · ')}
            </Typography>
          )}
        </Box>
      ))}
      {record.credit_hours && (
        <>
          <Typography variant="h6" component="h3" sx={{ mt: 3 }}>
            {t('summary')}
          </Typography>
          <TableContainer>
            <Table size="small" aria-label={t('summary')}>
              <TableHead>
                <TableRow>
                  <TableCell>{t('category')}</TableCell>
                  {metrics.map((metric) => (
                    <TableCell key={metric}>{t(`metrics.${metric}`)}</TableCell>
                  ))}
                </TableRow>
              </TableHead>
              <TableBody>
                {[
                  ...(record.credit_hours.categories ?? []),
                  ...(record.credit_hours.total ? [record.credit_hours.total] : []),
                ].map((row) => (
                  <TableRow key={row.category}>
                    <TableCell>{t(`categories.${row.category}`)}</TableCell>
                    {metrics.map((metric) => (
                      <TableCell key={metric}>{row[metric] ?? '—'}</TableCell>
                    ))}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </>
      )}
    </Box>
  )
}
