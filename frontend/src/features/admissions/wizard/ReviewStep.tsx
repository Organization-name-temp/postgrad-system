import { Alert, Box, Button, Divider, Paper, Stack, Typography } from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getMyApplicationQueryKey,
  useFinalizeTheApplication,
} from '../../../shared/api/generated/endpoints'
import type {
  MyApplication200Data,
  MyApplication200DataPrimaryProgram,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage, apiFieldErrors } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'

interface ReviewStepProps {
  application: MyApplication200Data
  onBack: () => void
}

/**
 * Step 5 of the applicant wizard: the review/confirm summary that precedes finalize
 * (FR-ADM-004.5). It shows the applicant their key details, chosen Program(s), and
 * uploaded documents, then finalizes — the server re-checks completeness (a primary
 * Program + every required and conditionally-required document) and, on success,
 * moves the application into Student Affairs review. A missing prerequisite comes
 * back as an inline 422; finalize is the deliberate, last act.
 */
export function ReviewStep({ application, onBack }: ReviewStepProps) {
  const { t, i18n } = useTranslation('admissions')
  const queryClient = useQueryClient()

  const finalize = useFinalizeTheApplication()

  const fieldErrors = apiFieldErrors(finalize.error)
  const generalError = finalize.isError
    ? (apiErrorMessage(finalize.error) ?? t('review.finalizeError'))
    : undefined

  const programName = (
    program: MyApplication200DataPrimaryProgram | undefined,
    missing: string,
  ): string => (program ? pickLocaleName(program, i18n.language, program.code ?? '') : missing)

  const documents = application.documents ?? []

  const handleFinalize = () => {
    finalize.mutate(undefined, {
      // On success the status becomes `under_sa_review`; refreshing the application
      // flips the wizard to its "submitted for review" state (driven by status).
      onSuccess: () => queryClient.invalidateQueries({ queryKey: getMyApplicationQueryKey() }),
    })
  }

  const row = (label: string, value: string) => (
    <Stack direction="row" spacing={1} sx={{ justifyContent: 'space-between' }}>
      <Typography variant="body2" color="text.secondary">
        {label}
      </Typography>
      <Typography variant="body2" sx={{ fontWeight: 500, textAlign: 'end' }}>
        {value}
      </Typography>
    </Stack>
  )

  return (
    <Box>
      <Typography variant="h6" gutterBottom>
        {t('review.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('review.intro')}
      </Typography>

      <Stack spacing={2} sx={{ mt: 2 }}>
        <Paper variant="outlined" sx={{ p: 2 }}>
          <Divider textAlign="left" sx={{ mb: 1 }}>
            {t('review.sections.applicant')}
          </Divider>
          <Stack spacing={0.5}>
            {row(t('personalInfo.fields.name_en'), application.name_en ?? '')}
            {row(t('personalInfo.fields.national_id'), application.national_id ?? '')}
            {row(
              t('personalInfo.fields.applying_for_degree'),
              application.applying_for_degree ?? '',
            )}
          </Stack>
        </Paper>

        <Paper variant="outlined" sx={{ p: 2 }}>
          <Divider textAlign="left" sx={{ mb: 1 }}>
            {t('review.sections.programs')}
          </Divider>
          <Stack spacing={0.5}>
            {row(t('program.primaryLabel'), programName(application.primary_program, '—'))}
            {row(
              t('program.alternativeLabel'),
              programName(application.alternative_program, t('review.noAlternative')),
            )}
          </Stack>
        </Paper>

        <Paper variant="outlined" sx={{ p: 2 }}>
          <Divider textAlign="left" sx={{ mb: 1 }}>
            {t('review.sections.documents')}
          </Divider>
          <Typography variant="body2">
            {t('review.documentsUploaded', { count: documents.length })}
          </Typography>
        </Paper>
      </Stack>

      {generalError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {generalError}
        </Alert>
      )}
      {fieldErrors.documents && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {fieldErrors.documents[0]}
        </Alert>
      )}
      {fieldErrors.primary_program_id && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {fieldErrors.primary_program_id[0]}
        </Alert>
      )}

      <Stack direction="row" spacing={2} sx={{ mt: 3 }}>
        <Button variant="outlined" onClick={onBack} disabled={finalize.isPending}>
          {t('review.back')}
        </Button>
        <Button variant="contained" onClick={handleFinalize} disabled={finalize.isPending}>
          {finalize.isPending ? t('review.finalizing') : t('review.finalize')}
        </Button>
      </Stack>
    </Box>
  )
}
