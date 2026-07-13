import {
  Alert,
  Box,
  CircularProgress,
  List,
  ListItem,
  ListItemText,
  Typography,
} from '@mui/material'
import HistoryIcon from '@mui/icons-material/History'
import { useTranslation } from 'react-i18next'
import type { ListAProgramsVersionHistory200DataItem } from '../../../shared/api/generated/endpoints'

/**
 * One version-history row. Derived from the generated contract rather than
 * hand-written (architecture.md §7) — the Program and Course version endpoints
 * return structurally identical items, so either generated item type satisfies
 * this, and we pick the fields the panel renders.
 */
export type VersionEntry = Pick<
  ListAProgramsVersionHistory200DataItem,
  'id' | 'version_no' | 'change_note' | 'changed_at'
>

interface VersionHistoryPanelProps {
  versions: VersionEntry[]
  isPending: boolean
  isError: boolean
}

const CHANGE_NOTES = ['created', 'updated', 'activated', 'deactivated', 'curriculum_updated']

/**
 * A read-only version-history panel for a Program or Course (ADR-0011). Shows the
 * sequence of versions newest-first — version number, the change that produced it
 * (a translated action label), and when — to any catalog.view holder (story 35).
 * Presentational: the hosting page fetches from the subject's versions endpoint and
 * passes the result in. History is append-only and cannot be edited here (the API
 * has no write verbs for it).
 */
export function VersionHistoryPanel({ versions, isPending, isError }: VersionHistoryPanelProps) {
  const { t, i18n } = useTranslation('catalog')

  const dateFormatter = new Intl.DateTimeFormat(i18n.language, {
    dateStyle: 'medium',
    timeStyle: 'short',
  })

  const changeLabel = (note?: string): string =>
    note && CHANGE_NOTES.includes(note) ? t(`versions.changeNotes.${note}`) : (note ?? '')

  // Newest first — the most recent change is the most interesting.
  const ordered = [...versions].sort((a, b) => (b.version_no ?? 0) - (a.version_no ?? 0))

  return (
    <Box component="section" data-testid="version-history">
      <Typography
        variant="h5"
        component="h2"
        sx={{ mb: 1, display: 'flex', alignItems: 'center', gap: 1 }}
      >
        <HistoryIcon fontSize="small" />
        {t('versions.title')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('versions.loading')} />}
      {isError && <Alert severity="error">{t('versions.loadError')}</Alert>}

      {!isPending && !isError && ordered.length === 0 && (
        <Typography color="text.secondary" variant="body2">
          {t('versions.empty')}
        </Typography>
      )}

      {ordered.length > 0 && (
        <List dense>
          {ordered.map((version) => (
            <ListItem key={version.id} disableGutters>
              <ListItemText
                primary={t('versions.versionLabel', {
                  number: version.version_no ?? 0,
                  change: changeLabel(version.change_note),
                })}
                secondary={
                  version.changed_at ? dateFormatter.format(new Date(version.changed_at)) : ''
                }
              />
            </ListItem>
          ))}
        </List>
      )}
    </Box>
  )
}
