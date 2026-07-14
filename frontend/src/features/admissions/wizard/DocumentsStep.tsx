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
import { DOCUMENT_TYPES } from './documentTypes'
import type { DocumentRequirement, DocumentTypeConfig } from './documentTypes'

interface DocumentsStepProps {
  application: MyApplication200Data
}

/** Requirement → the MUI Chip colour that conveys how strongly it is needed. */
const REQUIREMENT_COLOR: Record<DocumentRequirement, 'error' | 'warning' | 'default'> = {
  required: 'error',
  conditional: 'warning',
  optional: 'default',
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
export function DocumentsStep({ application }: DocumentsStepProps) {
  const { t } = useTranslation('admissions')
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
      window.open(document.download_url, '_blank', 'noopener,noreferrer')
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
                  color={REQUIREMENT_COLOR[config.requirement]}
                  label={t(`documents.requirements.${config.requirement}`)}
                  variant={config.requirement === 'optional' ? 'outlined' : 'filled'}
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
    </Box>
  )
}
