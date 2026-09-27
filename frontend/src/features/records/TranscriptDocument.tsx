import { LtrValue, RecordTime } from './RecordValue'
import { transcriptPrintSx } from './transcriptPrint'
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
import { useTheme } from '@mui/material/styles'
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
  const theme = useTheme()
  const arabic = (language ?? i18n.language) === 'ar'
  // This duplicates the readable official heading as a decorative graphic.
  // An empty image alternative keeps it out of the document's reading order.
  const watermark = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 80"><text x="300" y="40" text-anchor="middle" dominant-baseline="middle" fill="${theme.palette.common.black}" font-family="${theme.typography.fontFamily?.replaceAll('"', '&quot;')}" style="font-size:${theme.typography.h2.fontSize};font-weight:${theme.typography.fontWeightBold}">${t('service.watermark')}</text></svg>`,
  )}`
  const name = (value: { name_en?: string; name_ar?: string } | undefined) =>
    arabic ? value?.name_ar : value?.name_en
  const program = record.program
  const admissionTerm = record.enrollment_term?.replace(
    /^(Fall|Spring|Summer)(\s+\d{4})$/,
    (_, term: string, year: string) => `${t(`terms.${term}`)}${year}`,
  )
  const number = (value: number | null | undefined) => (value == null ? '—' : value.toFixed(2))
  return (
    <Box
      component="article"
      dir={arabic ? 'rtl' : 'ltr'}
      sx={[
        {
          position: 'relative',
          bgcolor: 'background.paper',
          color: 'text.primary',
          p: 2,
          '@media print': { p: 0 },
        },
        transcriptPrintSx,
      ]}
    >
      {official && (
        <Box
          component="img"
          src={watermark}
          alt=""
          data-testid="official-watermark"
          aria-hidden="true"
          sx={{
            position: 'absolute',
            insetInline: 0,
            top: '40%',
            width: '100%',
            transform: 'rotate(-25deg)',
            opacity: 0.08,
            pointerEvents: 'none',
            userSelect: 'none',
            ...theme.applyStyles('dark', { filter: 'invert(1)' }),
            '@media print': { filter: 'none !important' },
          }}
        />
      )}
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
        {name(program)} · <LtrValue>{program?.degree_level}</LtrValue>
      </Typography>
      <Typography>
        {arabic ? program?.department_name_ar : program?.department_name_en} ·{' '}
        {arabic ? program?.faculty_name_ar : program?.faculty_name_en}
      </Typography>
      <Typography>
        {admissionTerm} · {t(`lifecycle.${record.lifecycle_status}`)}
      </Typography>
      <Typography>
        {t('cgpa')}: <LtrValue>{number(record.cgpa)}</LtrValue>
      </Typography>
      {(record.semesters ?? []).map((semester) => (
        <Box key={semester.semester?.id} sx={{ mt: 3 }}>
          <Typography variant="h6" component="h3">
            {name(semester.semester)}
          </Typography>
          <Typography>
            {t('semesterGpa')}: <LtrValue>{number(semester.gpa)}</LtrValue>
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
                    <TableCell>
                      <LtrValue>{course.credit_hours}</LtrValue>
                    </TableCell>
                    <TableCell>{t(`classifications.${course.classification}`)}</TableCell>
                    <TableCell>
                      {course.result === 'InProgress' ? (
                        t('inProgress')
                      ) : (
                        <LtrValue>{course.result}</LtrValue>
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
            {t(`decisions.${decision.type}`)} · <LtrValue>{decision.target_course_code}</LtrValue> ·{' '}
            <LtrValue>{decision.credit_hours}</LtrValue> {t('creditHours')} ·{' '}
            <RecordTime value={decision.approved_at} dateOnly language={language} />
          </Typography>
          {decision.type === 'Transfer' && (
            <Typography>
              {decision.source_institution} · <LtrValue>{decision.source_course_code}</LtrValue> ·{' '}
              {decision.source_course_name} · <LtrValue>{decision.original_grade}</LtrValue> ·{' '}
              <LtrValue>{decision.normalized_grade}</LtrValue>
            </Typography>
          )}
          {decision.type === 'Waiver' && (
            <Typography>
              <LtrValue>{decision.prior_course_code}</LtrValue> ·{' '}
              <LtrValue>{decision.prior_grade}</LtrValue>
            </Typography>
          )}
          {decision.type === 'Exemption' && (
            <Typography>
              {(decision.reason_codes ?? [])
                .map((reason) =>
                  t(`enrollment:exemptions.reasons.${reason}`, { defaultValue: reason }),
                )
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
                      <TableCell key={metric}>
                        <LtrValue>{row[metric] ?? '—'}</LtrValue>
                      </TableCell>
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
