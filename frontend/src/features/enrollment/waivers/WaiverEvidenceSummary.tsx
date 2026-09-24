import { Stack, Typography } from '@mui/material'
import { Trans, useTranslation } from 'react-i18next'

type Evidence = {
  source?: string
  enrollment_id?: number | null
  course_id?: number | null
  letter_grade?: string | null
  official_transcript_document_ids?: number[]
}

export function WaiverEvidenceSummary({
  evidence,
  priorCourseCode,
  priorCourseLevel,
}: {
  evidence?: Evidence
  priorCourseCode?: string
  priorCourseLevel?: number
}) {
  const { t } = useTranslation('enrollment')
  const documents = evidence?.official_transcript_document_ids?.join(', ')

  return (
    <Stack spacing={0.5}>
      <Typography color="text.secondary">
        <Trans
          t={t}
          i18nKey="waivers.evidence.course"
          values={{ course: priorCourseCode, level: priorCourseLevel }}
          components={{ code: <bdi dir="ltr" />, number: <bdi dir="ltr" /> }}
        />
      </Typography>
      <Typography color="text.secondary">
        {evidence?.source === 'legacy_transcript' ? (
          <Trans
            t={t}
            i18nKey="waivers.evidence.legacy"
            values={{ grade: evidence.letter_grade, documents }}
            components={{ grade: <bdi dir="ltr" />, documents: <bdi dir="ltr" /> }}
          />
        ) : (
          <Trans
            t={t}
            i18nKey="waivers.evidence.prs"
            values={{ enrollment: evidence?.enrollment_id }}
            components={{ number: <bdi dir="ltr" /> }}
          />
        )}
      </Typography>
    </Stack>
  )
}
