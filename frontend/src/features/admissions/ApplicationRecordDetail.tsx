import type { ReactNode } from 'react'
import {
  Divider,
  Link,
  List,
  ListItem,
  ListItemText,
  Paper,
  Stack,
  Typography,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import type {
  ReviewAnApplication200Data,
  StatusTimeline200DataItem,
} from '../../shared/api/generated/endpoints'
import { formatDate } from '../../shared/datetime'
import { DetailRow, useReviewFormatting } from './reviewFormatting'
import { openDocument } from './openDocument'
import { StatusTimeline } from './StatusTimeline'

interface ApplicationRecordDetailProps {
  /** The full application record — the reviewer endpoint returns every captured field. */
  application: ReviewAnApplication200Data
  /** The status timeline read, surfaced alongside the record. */
  timeline: {
    entries: StatusTimeline200DataItem[]
    isPending: boolean
    isError: boolean
  }
}

/**
 * The complete applicant record for a reviewer (Student Affairs or a Dean) to assess —
 * identity, contact & address, full academic background, English proficiency, the chosen
 * Program(s), every uploaded document (opened via the ticket-01 authenticated fetch), and
 * the status timeline — grouped into readable sections (Phase 2.5 ticket 09). Shared by
 * {@link ApplicationReviewPage} and {@link DeanReviewPage}, which each own the surrounding
 * header and decision panel; this is the read-only record between them, replacing the old
 * six-field summary. The API already returns the whole payload, so this closes a display
 * gap, not a data one.
 */
export function ApplicationRecordDetail({ application, timeline }: ApplicationRecordDetailProps) {
  const { t, i18n } = useTranslation('admissions')
  const { shown, bool, enumLabel, programName } = useReviewFormatting()

  const documents = application.documents ?? []

  const row = (label: string, value: string) => <DetailRow label={label} value={value} />

  const field = (name: keyof ReviewAnApplication200Data, value: string) =>
    row(t(`personalInfo.fields.${name}`), value)

  const section = (title: string, children: ReactNode) => (
    <Paper variant="outlined" sx={{ p: 2 }}>
      <Divider textAlign="left" sx={{ mb: 1 }}>
        {title}
      </Divider>
      <Stack spacing={0.5}>{children}</Stack>
    </Paper>
  )

  return (
    <Stack spacing={2}>
      {section(
        t('review.sections.identity'),
        <>
          {field('national_id', shown(application.national_id))}
          {field('id_type', enumLabel('personalInfo.idTypes', application.id_type))}
          {field('date_of_birth', shown(formatDate(application.date_of_birth, i18n.language)))}
          {field('nationality', shown(application.nationality))}
          {field('gender', enumLabel('personalInfo.genders', application.gender))}
        </>,
      )}

      {section(
        t('review.sections.contact'),
        <>
          {field('phone', shown(application.phone))}
          {field('address_country', shown(application.address_country))}
          {field('address_city', shown(application.address_city))}
          {field('address_street', shown(application.address_street))}
        </>,
      )}

      {section(
        t('review.sections.academic'),
        <>
          {field('previous_university', shown(application.previous_university))}
          {field('graduation_certificate_name', shown(application.graduation_certificate_name))}
          {field('graduation_date', shown(formatDate(application.graduation_date, i18n.language)))}
          {field('previous_degree', enumLabel('personalInfo.degrees', application.previous_degree))}
          {field('previous_major', shown(application.previous_major))}
          {field('graduation_gpa', shown(application.graduation_gpa))}
          {field('graduation_cgpa', shown(application.graduation_cgpa))}
          {field('gpa_scale', shown(application.gpa_scale))}
          {field('is_ejust_graduate', bool(application.is_ejust_graduate))}
          {field(
            'applying_for_degree',
            enumLabel('personalInfo.degrees', application.applying_for_degree),
          )}
        </>,
      )}

      {section(
        t('review.sections.english'),
        <>
          {field('english_test', shown(application.english_test))}
          {field('english_score', shown(application.english_score))}
          {field('native_arabic_speaker', bool(application.native_arabic_speaker))}
        </>,
      )}

      {section(
        t('review.sections.programs'),
        <>
          {row(t('program.primaryLabel'), programName(application.primary_program, t('review.empty')))}
          {row(
            t('program.alternativeLabel'),
            programName(application.alternative_program, t('review.noAlternative')),
          )}
        </>,
      )}

      <Paper variant="outlined" sx={{ p: 2 }}>
        <Divider textAlign="left" sx={{ mb: 1 }}>
          {t('review.sections.documents')}
        </Divider>
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
                  primary={
                    document.download_url ? (
                      <Link
                        component="button"
                        type="button"
                        onClick={() => {
                          if (document.download_url) void openDocument(document.download_url)
                        }}
                        sx={{ textAlign: 'start' }}
                      >
                        {document.original_filename}
                      </Link>
                    ) : (
                      document.original_filename
                    )
                  }
                  secondary={t(`documents.types.${document.document_type}`)}
                />
              </ListItem>
            ))}
          </List>
        )}
      </Paper>

      <Paper variant="outlined" sx={{ p: 2 }}>
        <StatusTimeline
          entries={timeline.entries}
          currentStatus={application.status}
          isPending={timeline.isPending}
          isError={timeline.isError}
        />
      </Paper>
    </Stack>
  )
}
