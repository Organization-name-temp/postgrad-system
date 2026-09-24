import { Link, Stack, Typography } from '@mui/material'
import { Trans, useTranslation } from 'react-i18next'

export type TransferEvidence = {
  external_institution?: string
  external_course_code?: string
  external_course_name?: string
  source_credit_hours?: string
  external_grade?: string
  document_ids?: Array<number | string>
  evidence_documents?: Array<{
    id?: string
    original_filename?: string
    download_url?: string
  }>
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
          components={{ hours: <bdi dir="ltr" /> }}
        />
      </Typography>
      {evidence.target_credit_hours !== undefined && (
        <Typography color="text.secondary">
          <Trans
            t={t}
            i18nKey="transfers.targetHours"
            values={{ count: evidence.target_credit_hours }}
            components={{ hours: <bdi dir="ltr" /> }}
          />
        </Typography>
      )}
      {evidence.normalized_grade && (
        <Typography>
          <Trans
            t={t}
            i18nKey="transfers.normalizedGradeValue"
            values={{ grade: evidence.normalized_grade }}
            components={{ grade: <bdi dir="ltr" /> }}
          />
        </Typography>
      )}
      {evidence.evidence_documents?.length ? (
        <Stack component="ul" spacing={0.5} sx={{ m: 0, ps: 2.5 }}>
          {evidence.evidence_documents.map((document) => (
            <Typography component="li" variant="body2" key={document.id}>
              <Link href={document.download_url} target="_blank" rel="noopener noreferrer">
                <bdi dir="ltr">{document.original_filename}</bdi>
              </Link>
            </Typography>
          ))}
        </Stack>
      ) : (
        <Typography variant="body2" color="text.secondary">
          <Trans
            t={t}
            i18nKey="transfers.documentsValue"
            values={{ documents: evidence.document_ids?.join(', ') }}
            components={{ documents: <bdi dir="ltr" /> }}
          />
        </Typography>
      )}
    </Stack>
  )
}
