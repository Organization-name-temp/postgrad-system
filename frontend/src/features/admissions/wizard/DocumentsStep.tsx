import { useState } from 'react'
import type { ChangeEvent } from 'react'
import {
  Alert,
  Box,
  Button,
  Chip,
  Divider,
  IconButton,
  Paper,
  Stack,
  Typography,
} from '@mui/material'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined'
import UploadFileIcon from '@mui/icons-material/UploadFile'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getMyApplicationQueryKey,
  useRemoveADocument,
  useUploadADocument,
} from '../../../shared/api/generated/endpoints'
import type {
  MyApplication200Data,
  MyApplication200DataDocumentsItem,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { openDocument } from '../openDocument'
import { DOCUMENT_TYPES } from './documentTypes'
import type { DocumentRequirement, DocumentTypeConfig } from './documentTypes'
import { ENGLISH_TEST_NONE } from './fieldOptions'

interface DocumentsStepProps {
  application: MyApplication200Data
  /** Advance to program selection. Omitted where the step is shown standalone. */
  onContinue?: () => void
  /** Return to the personal-info step to correct a field (Phase 2.5 B3). Omitted where the step is shown standalone. */
  onBack?: () => void
}

/** Requirement → the MUI Chip colour that conveys how strongly it is needed. */
const REQUIREMENT_COLOR: Record<DocumentRequirement, 'error' | 'warning' | 'default'> = {
  required: 'error',
  conditional: 'warning',
  optional: 'default',
}

/**
 * The requirement for a document given the applicant's own Step-1 answers. The English
 * certificate becomes Required once a test other than "None" is reported, and the MSc
 * thesis once the applicant is applying for a PhD — mirroring the backend's finalize
 * check (Application::requiredDocumentTypesForFinalize), so a conditional document
 * reads as Required the moment it is actually owed rather than looking optional until
 * finalize refuses it.
 */
function effectiveRequirement(
  config: DocumentTypeConfig,
  application: MyApplication200Data,
): DocumentRequirement {
  if (
    config.type === 'english_certificate' &&
    application.english_test != null &&
    application.english_test !== ENGLISH_TEST_NONE
  ) {
    return 'required'
  }
  if (config.type === 'msc_thesis' && application.applying_for_degree === 'PhD') {
    return 'required'
  }

  return config.requirement
}

/** A compact, human-readable file size (KB below 1 MB, otherwise MB). */
function formatSize(bytes: number | undefined): string {
  if (!bytes) return ''
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

/**
 * Step 3 of the applicant wizard: document upload (SRS §4.2 FR-ADM-003). Each
 * DocumentType gets its own widget showing its rules (how strongly it is required,
 * the accepted formats, the size limit, and how many copies are allowed); the
 * applicant uploads, previews, replaces, and removes files while the application is
 * still in their hands. The server re-validates every upload against the enum, and
 * surfaces a bad type/format/size/over-count as an inline 422 message. Documents
 * lock once the application enters review (the API then answers 409).
 */
export function DocumentsStep({ application, onContinue, onBack }: DocumentsStepProps) {
  const { t, i18n } = useTranslation('admissions')
  const queryClient = useQueryClient()

  // One upload/remove mutation drives every widget; `activeType` scopes the busy
  // state and any error message to the widget the applicant is acting on.
  const [activeType, setActiveType] = useState<string | null>(null)
  const upload = useUploadADocument()
  const remove = useRemoveADocument()

  const refresh = () => queryClient.invalidateQueries({ queryKey: getMyApplicationQueryKey() })

  const documents = application.documents ?? []
  const documentsOfType = (type: string): MyApplication200DataDocumentsItem[] =>
    documents.filter((document) => document.document_type === type)

  // Which documents this application must hold to proceed — the always-required set
  // plus any conditional documents the applicant's answers have promoted — and which
  // of those are still missing. Continue is gated on there being none outstanding.
  const missingRequired = DOCUMENT_TYPES.filter(
    (config) =>
      effectiveRequirement(config, application) === 'required' &&
      documentsOfType(config.type).length === 0,
  )
  const canContinue = missingRequired.length === 0

  const handleUpload = (config: DocumentTypeConfig, event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    event.target.value = '' // reset so re-picking the same file still fires onChange
    if (!file) return

    setActiveType(config.type)
    upload.mutate({ data: { document_type: config.type, file } }, { onSuccess: refresh })
  }

  const handleRemove = (document: MyApplication200DataDocumentsItem) => {
    if (!document.id) return
    setActiveType(document.document_type ?? null)
    remove.mutate({ documentUlid: document.id }, { onSuccess: refresh })
  }

  const preview = (document: MyApplication200DataDocumentsItem) => {
    if (document.download_url) {
      // Fetched through the authenticated client and opened as a blob — a direct
      // link carries no auth context and is refused (ADR-0016).
      void openDocument(document.download_url)
    }
  }

  return (
    <Box>
      <Typography variant="h6" gutterBottom>
        {t('documents.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('documents.intro')}
      </Typography>

      <Stack spacing={2} sx={{ mt: 2 }}>
        {DOCUMENT_TYPES.map((config) => {
          const uploaded = documentsOfType(config.type)
          const requirement = effectiveRequirement(config, application)
          const atCap = uploaded.length >= config.maxCount
          const busy = activeType === config.type && (upload.isPending || remove.isPending)
          const error =
            activeType === config.type && upload.isError
              ? (apiErrorMessage(upload.error) ?? t('documents.uploadError'))
              : undefined

          return (
            <Paper key={config.type} variant="outlined" sx={{ p: 2 }}>
              <Stack
                direction="row"
                spacing={1}
                sx={{ alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap' }}
              >
                <Box>
                  <Typography variant="subtitle1">{t(`documents.types.${config.type}`)}</Typography>
                  <Typography variant="caption" color="text.secondary">
                    {t('documents.hint', { formats: config.formats, size: config.maxSizeMb })}
                    {config.maxCount > 1
                      ? ` · ${t('documents.upToCount', { count: config.maxCount })}`
                      : ''}
                  </Typography>
                </Box>
                <Chip
                  size="small"
                  color={REQUIREMENT_COLOR[requirement]}
                  label={t(`documents.requirements.${requirement}`)}
                  variant={requirement === 'optional' ? 'outlined' : 'filled'}
                />
              </Stack>

              {uploaded.length > 0 && (
                <Stack spacing={1} sx={{ mt: 2 }} divider={<Divider flexItem />}>
                  {uploaded.map((document) => (
                    <Stack
                      key={document.id}
                      direction="row"
                      spacing={1}
                      sx={{ alignItems: 'center', justifyContent: 'space-between' }}
                    >
                      <Typography variant="body2" sx={{ wordBreak: 'break-all' }}>
                        {document.original_filename}
                        <Typography component="span" variant="caption" color="text.secondary">
                          {' '}
                          · {formatSize(document.size_bytes)}
                        </Typography>
                      </Typography>
                      <Stack direction="row" spacing={0.5}>
                        <IconButton
                          size="small"
                          aria-label={t('documents.preview')}
                          onClick={() => preview(document)}
                        >
                          <VisibilityOutlinedIcon fontSize="small" />
                        </IconButton>
                        <IconButton
                          size="small"
                          aria-label={t('documents.remove')}
                          onClick={() => handleRemove(document)}
                          disabled={busy}
                        >
                          <DeleteOutlineIcon fontSize="small" />
                        </IconButton>
                      </Stack>
                    </Stack>
                  ))}
                </Stack>
              )}

              {error && (
                <Alert severity="error" sx={{ mt: 2 }}>
                  {error}
                </Alert>
              )}

              {!atCap && (
                <Button
                  component="label"
                  variant="outlined"
                  size="small"
                  startIcon={<UploadFileIcon />}
                  disabled={busy}
                  sx={{ mt: 2 }}
                >
                  {busy
                    ? t('documents.uploading')
                    : uploaded.length > 0
                      ? t('documents.addAnother')
                      : t('documents.upload')}
                  <input
                    type="file"
                    hidden
                    accept={config.accept}
                    onChange={(event) => handleUpload(config, event)}
                  />
                </Button>
              )}
            </Paper>
          )
        })}
      </Stack>

      <Alert severity="info" sx={{ mt: 3 }}>
        {t('documents.nextStep')}
      </Alert>

      {onContinue && !canContinue && (
        <Alert severity="warning" sx={{ mt: 2 }}>
          {t('documents.missingRequired', {
            documents: new Intl.ListFormat(i18n.language, {
              style: 'long',
              type: 'conjunction',
            }).format(missingRequired.map((config) => t(`documents.types.${config.type}`))),
          })}
        </Alert>
      )}

      {(onBack || onContinue) && (
        <Stack
          direction="row"
          spacing={2}
          sx={{ mt: 3, justifyContent: onBack ? 'space-between' : 'flex-end' }}
        >
          {onBack && (
            <Button variant="outlined" onClick={onBack}>
              {t('documents.back')}
            </Button>
          )}
          {onContinue && (
            <Button variant="contained" onClick={onContinue} disabled={!canContinue}>
              {t('documents.continue')}
            </Button>
          )}
        </Stack>
      )}
    </Box>
  )
}
