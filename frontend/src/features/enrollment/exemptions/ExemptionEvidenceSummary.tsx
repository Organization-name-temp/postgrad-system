import { Typography } from '@mui/material'
import { Trans, useTranslation } from 'react-i18next'

type ExemptionEvidence = {
  english_test?: string
  english_score?: number
  previous_degree?: string
  previous_ejust_program_id?: number
}

export function ExemptionEvidenceSummary({ evidence }: { evidence?: ExemptionEvidence }) {
  const { t } = useTranslation('enrollment')

  if (evidence?.english_test) {
    return (
      <Typography color="text.secondary">
        <Trans
          t={t}
          i18nKey="manualExemptions.evidenceSummary.english"
          values={{ test: evidence.english_test, score: evidence.english_score }}
          components={{ code: <bdi dir="ltr" />, number: <bdi dir="ltr" /> }}
        />
      </Typography>
    )
  }

  return (
    <Typography color="text.secondary">
      {evidence?.previous_ejust_program_id ? (
        <Trans
          t={t}
          i18nKey="manualExemptions.evidenceSummary.previousDegreeAndProgram"
          values={{
            degree: evidence.previous_degree,
            program: evidence.previous_ejust_program_id,
          }}
          components={{ code: <bdi dir="ltr" />, number: <bdi dir="ltr" /> }}
        />
      ) : (
        <Trans
          t={t}
          i18nKey="manualExemptions.evidenceSummary.previousDegree"
          values={{ degree: evidence?.previous_degree }}
          components={{ code: <bdi dir="ltr" /> }}
        />
      )}
    </Typography>
  )
}
