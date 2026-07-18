import type { ReactNode } from 'react'
import {
  Alert,
  Box,
  Button,
  Divider,
  List,
  ListItem,
  ListItemText,
  Paper,
  Stack,
  Typography,
} from '@mui/material'
import EditIcon from '@mui/icons-material/Edit'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getMyApplicationQueryKey,
  useFinalizeTheApplication,
  useResubmitAfterRevision,
} from '../../../shared/api/generated/endpoints'
import type { MyApplication200Data } from '../../../shared/api/generated/endpoints'
import { apiErrorMessage, apiFieldErrors } from '../../../shared/api/errors'
import { formatDate } from '../../../shared/datetime'
import { DetailRow, useReviewFormatting } from '../reviewFormatting'

/** The wizard steps the applicant can jump back to and edit from the Review summary. */
export type ReviewEditStep = 'personal' | 'documents' | 'program'

interface ReviewStepProps {
  application: MyApplication200Data
  onBack: () => void
  /**
   * Whether this is a first-time `finalize` (default) or a `resubmit` of an
   * application that was returned for revision (ticket 07). Both re-check the
   * summary; finalize moves email_verified → under_sa_review, resubmit moves
   * returned_for_revision → under_sa_review.
   */
  mode?: 'finalize' | 'resubmit'
  /**
   * Jump back to a specific wizard step to correct that section, returning to Review
   * afterwards (ticket 08). When omitted, sections render without an Edit affordance.
   */
  onEdit?: (step: ReviewEditStep) => void
}

/**
 * The review/confirm summary that precedes the last applicant action (FR-ADM-004.5).
 * It reads the whole application back to the applicant — the complete captured record
 * grouped into sections (identity, contact & address, academic background, English
 * proficiency), their chosen Program(s), and every uploaded document by filename and
 * type — then either **finalizes** a fresh application or **resubmits** a returned one.
 * The server re-checks completeness on finalize (a primary Program + every required and
 * conditionally-required document) and, on success, moves the application into Student
 * Affairs review. A missing prerequisite comes back as an inline 422; this is the
 * deliberate, last act before review.
 */
export function ReviewStep({ application, onBack, mode = 'finalize', onEdit }: ReviewStepProps) {
  const { t, i18n } = useTranslation('admissions')
  const queryClient = useQueryClient()

  const finalize = useFinalizeTheApplication()
  const resubmit = useResubmitAfterRevision()
  const submission = mode === 'resubmit' ? resubmit : finalize

  const fieldErrors = apiFieldErrors(submission.error)
  const generalError = submission.isError
    ? (apiErrorMessage(submission.error) ??
      t(mode === 'resubmit' ? 'wizard.resubmitError' : 'review.finalizeError'))
    : undefined

  const { shown, bool, enumLabel, programName } = useReviewFormatting()

  const documents = application.documents ?? []

  const handleSubmit = () => {
    // On success the status becomes `under_sa_review`; refreshing the application
    // flips the wizard to its read-only status view (driven by status).
    const onSuccess = () => queryClient.invalidateQueries({ queryKey: getMyApplicationQueryKey() })
    if (mode === 'resubmit') {
      resubmit.mutate(undefined, { onSuccess })
    } else {
      finalize.mutate(undefined, { onSuccess })
    }
  }

  const row = (label: string, value: string) => <DetailRow label={label} value={value} />

  const field = (name: keyof MyApplication200Data, value: string) =>
    row(t(`personalInfo.fields.${name}`), value)

  // A section header: the divider title, plus a per-section Edit action that jumps back
  // to that step (and returns here) when the wizard supplies an `onEdit` (ticket 08).
  const sectionHeader = (title: string, editStep?: ReviewEditStep) => (
    <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 1 }}>
      <Divider textAlign="left" sx={{ flexGrow: 1 }}>
        {title}
      </Divider>
      {onEdit && editStep && (
        <Button
          size="small"
          startIcon={<EditIcon fontSize="small" />}
          onClick={() => onEdit(editStep)}
        >
          {t('review.edit')}
        </Button>
      )}
    </Stack>
  )

  const section = (title: string, children: ReactNode, editStep?: ReviewEditStep) => (
    <Paper variant="outlined" sx={{ p: 2 }}>
      {sectionHeader(title, editStep)}
      <Stack spacing={0.5}>{children}</Stack>
    </Paper>
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
        {section(
          t('review.sections.identity'),
          <>
            {field('name_en', shown(application.name_en))}
            {field('name_ar', shown(application.name_ar))}
            {field('national_id', shown(application.national_id))}
            {field('date_of_birth', shown(formatDate(application.date_of_birth, i18n.language)))}
            {field('nationality', shown(application.nationality))}
            {field('gender', enumLabel('personalInfo.genders', application.gender))}
          </>,
          'personal',
        )}

        {section(
          t('review.sections.contact'),
          <>
            {field('phone', shown(application.phone))}
            {field('address_country', shown(application.address_country))}
            {field('address_city', shown(application.address_city))}
            {field('address_street', shown(application.address_street))}
          </>,
          'personal',
        )}

        {section(
          t('review.sections.academic'),
          <>
            {field('previous_university', shown(application.previous_university))}
            {field('graduation_certificate_name', shown(application.graduation_certificate_name))}
            {field(
              'graduation_date',
              shown(formatDate(application.graduation_date, i18n.language)),
            )}
            {field(
              'previous_degree',
              enumLabel('personalInfo.degrees', application.previous_degree),
            )}
            {field('previous_major', shown(application.previous_major))}
            {field('graduation_gpa', shown(application.graduation_gpa))}
            {field('graduation_cgpa', shown(application.graduation_cgpa))}
            {field('gpa_scale', shown(application.gpa_scale))}
            {field(
              'applying_for_degree',
              enumLabel('personalInfo.degrees', application.applying_for_degree),
            )}
            {field('is_ejust_graduate', bool(application.is_ejust_graduate))}
          </>,
          'personal',
        )}

        {section(
          t('review.sections.english'),
          <>
            {field('english_test', shown(application.english_test))}
            {field('english_score', shown(application.english_score))}
            {field('native_arabic_speaker', bool(application.native_arabic_speaker))}
          </>,
          'personal',
        )}

        {section(
          t('review.sections.programs'),
          <>
            {row(
              t('program.primaryLabel'),
              programName(application.primary_program, t('review.empty')),
            )}
            {row(
              t('program.alternativeLabel'),
              programName(application.alternative_program, t('review.noAlternative')),
            )}
          </>,
          'program',
        )}

        <Paper variant="outlined" sx={{ p: 2 }}>
          {sectionHeader(t('review.sections.documents'), 'documents')}
          <Typography variant="body2" gutterBottom>
            {t('review.documentsUploaded', { count: documents.length })}
          </Typography>
          {documents.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              {t('review.documentsEmpty')}
            </Typography>
          ) : (
            <List dense disablePadding>
              {documents.map((document) => (
                <ListItem key={document.id} disableGutters>
                  <ListItemText
                    primary={document.original_filename}
                    secondary={t(`documents.types.${document.document_type}`)}
                  />
                </ListItem>
              ))}
            </List>
          )}
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
        <Button variant="outlined" onClick={onBack} disabled={submission.isPending}>
          {t('review.back')}
        </Button>
        <Button variant="contained" onClick={handleSubmit} disabled={submission.isPending}>
          {mode === 'resubmit'
            ? submission.isPending
              ? t('wizard.resubmitting')
              : t('wizard.resubmit')
            : submission.isPending
              ? t('review.finalizing')
              : t('review.finalize')}
        </Button>
      </Stack>
    </Box>
  )
}
