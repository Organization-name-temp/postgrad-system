import { Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { pickLocaleName } from '../../shared/i18n/localeName'

/**
 * The shared read-back formatting for the application summaries — the applicant's
 * {@link ReviewStep} and the reviewer's {@link ApplicationRecordDetail} present the same
 * captured record and so format its values identically (Phase 2.5). This centralises that
 * formatting so the two never drift: a blank value reads as an em dash, a boolean as
 * Yes/No, an enum by its localized label, a Program by its localized name.
 */
export function useReviewFormatting() {
  const { t, i18n } = useTranslation('admissions')

  return {
    /** A blank/missing value reads as an em dash, not a gap mistakable for a real (empty) answer. */
    shown: (value: string | number | null | undefined): string =>
      value === null || value === undefined || value === '' ? t('review.empty') : String(value),

    /** A boolean as its localized Yes/No. */
    bool: (value: boolean | undefined): string => t(value ? 'review.yes' : 'review.no'),

    /** An enum value by its localized label under `prefix`, falling back to the raw value. */
    enumLabel: (prefix: string, value: string | undefined): string =>
      value ? t(`${prefix}.${value}`, { defaultValue: value }) : t('review.empty'),

    /** A Program by its localized name, or `missing` when there is no Program. */
    programName: (
      program: { name_en?: string; name_ar?: string; code?: string } | undefined,
      missing: string,
    ): string => (program ? pickLocaleName(program, i18n.language, program.code ?? '') : missing),
  }
}

/** A label/value row — the shared read-back line for the review and reviewer screens. */
export function DetailRow({ label, value }: { label: string; value: string }) {
  return (
    <Stack direction="row" spacing={1} sx={{ justifyContent: 'space-between' }}>
      <Typography variant="body2" color="text.secondary">
        {label}
      </Typography>
      <Typography variant="body2" sx={{ fontWeight: 500, textAlign: 'end' }}>
        {value}
      </Typography>
    </Stack>
  )
}
