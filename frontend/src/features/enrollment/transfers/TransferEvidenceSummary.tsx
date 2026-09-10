import { Stack, Typography } from '@mui/material'
import { Trans, useTranslation } from 'react-i18next'

export type TransferEvidence = {
  external_institution?: string
  external_course_code?: string
  external_course_name?: string
  source_credit_hours?: string
  external_grade?: string
  document_ids?: number[]
  target_credit_hours?: number
  normalized_grade?: string | null
}

export function TransferEvidenceSummary({ evidence }: { evidence: TransferEvidence }) {
  const { t } = useTranslation('enrollment')

  return (
    <Stack spacing={0.5}>
      <Typography>
        <Trans
          t={t}
          i18nKey="transfers.evidenceCourse"
          values={{
            institution: evidence.external_institution,
            code: evidence.external_course_code,
            name: evidence.external_course_name,
          }}
          components={{ code: <bdi dir="ltr" /> }}
        />
      </Typography>
      <Typography color="text.secondary">
        <Trans
          t={t}
          i18nKey="transfers.evidenceValues"
          values={{ hours: evidence.source_credit_hours, grade: evidence.external_grade }}
          components={{ value: <bdi dir="ltr" /> }}
        />
      </Typography>
      {evidence.target_credit_hours !== undefined && (
        <Typography color="text.secondary">
          {t('transfers.targetHours', { count: evidence.target_credit_hours })}
        </Typography>
      )}
      {evidence.normalized_grade && (
        <Typography>
          {t('transfers.normalizedGradeValue', { grade: evidence.normalized_grade })}
        </Typography>
      )}
      <Typography variant="body2" color="text.secondary">
        {t('transfers.documentsValue', { documents: evidence.document_ids?.join(', ') })}
      </Typography>
    </Stack>
  )
}
