import { Link, Stack, Typography } from '@mui/material'
import { Trans, useTranslation } from 'react-i18next'

type Evidence = {
  source?: string
  enrollment_id?: number | null
  course_id?: number | null
  letter_grade?: string | null
  official_transcript_document_ids?: string[]
}

export type WaiverEvidenceDocument = {
  id?: string
  original_filename?: string
  download_url?: string
  onOpen?: () => void
  pending?: boolean
}

export function WaiverEvidenceSummary({
  evidence,
  evidenceDocuments,
  priorCourseCode,
  priorCourseLevel,
}: {
  evidence?: Evidence
  evidenceDocuments?: WaiverEvidenceDocument[]
  priorCourseCode?: string
  priorCourseLevel?: number
}) {
  const { t } = useTranslation('enrollment')
  const documentIds = evidence?.official_transcript_document_ids ?? []

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
      {evidence?.source === 'legacy_transcript' ? (
        <Stack spacing={0.5}>
          <Typography color="text.secondary">
            <Trans
              t={t}
              i18nKey="waivers.evidence.legacy"
              values={{ grade: evidence.letter_grade }}
              components={{ grade: <bdi dir="ltr" /> }}
            />
          </Typography>
          {(evidenceDocuments?.length
            ? evidenceDocuments
            : documentIds.map<WaiverEvidenceDocument>((id) => ({ id }))
          ).map((document) =>
            document.download_url ? (
              <Link key={document.id} href={document.download_url} target="_blank" rel="noreferrer">
                <bdi dir="ltr">{document.original_filename ?? document.id}</bdi>
              </Link>
            ) : document.onOpen ? (
              <Link
                key={document.id}
                component="button"
                type="button"
                disabled={document.pending}
                onClick={document.onOpen}
              >
                <bdi dir="ltr">{document.original_filename ?? document.id}</bdi>
              </Link>
            ) : (
              <Typography key={document.id} variant="body2" color="text.secondary">
                <bdi dir="ltr">{document.original_filename ?? document.id}</bdi>
              </Typography>
            ),
          )}
        </Stack>
      ) : (
        <Typography color="text.secondary">
          <Trans
            t={t}
            i18nKey="waivers.evidence.prs"
            values={{ enrollment: evidence?.enrollment_id }}
            components={{ number: <bdi dir="ltr" /> }}
          />
        </Typography>
      )}
    </Stack>
  )
}
