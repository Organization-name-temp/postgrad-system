import InboxOutlined from '@mui/icons-material/InboxOutlined'
import { RecordTime } from './RecordValue'
import { RecordStatus } from './RecordStatus'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Alert,
  Button,
  MenuItem,
  Pagination,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import {
  useScopedOfficialIssuanceHistory,
  exportScopedIssuanceHistoryWithoutBearerTokens,
} from '../../shared/api/generated/endpoints'
import { downloadBlob } from '../../shared/downloadBlob'

export function IssuanceHistoryPage() {
  const { t } = useTranslation('records')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState(false)
  const params = { q: search || undefined, status: status || undefined, page, per_page: 25 }
  const query = useScopedOfficialIssuanceHistory(params)
  return (
    <Stack spacing={2}>
      <Typography variant="h4" component="h1">
        {t('service.history')}
      </Typography>
      <TextField
        label={t('search')}
        value={search}
        onChange={(event) => {
          setSearch(event.target.value)
          setPage(1)
        }}
      />
      <TextField
        select
        label={t('service.status')}
        value={status}
        onChange={(event) => {
          setStatus(event.target.value)
          setPage(1)
        }}
      >
        <MenuItem value="">{t('service.allStatuses')}</MenuItem>
        {['Valid', 'Revoked'].map((value) => (
          <MenuItem key={value} value={value}>
            {t(`service.statuses.${value}`)}
          </MenuItem>
        ))}
      </TextField>
      <Button
        onClick={() => {
          void exportScopedIssuanceHistoryWithoutBearerTokens(params)
            .then((blob) => downloadBlob(blob, 'transcript-issuances.xlsx'))
            .catch(() => setError(true))
        }}
      >
        {t('service.exportHistory')}
      </Button>
      {query.data?.data?.map((issuance) => (
        <Stack key={issuance.id}>
          <Typography dir="ltr">
            {issuance.reference} · <RecordTime value={issuance.issued_at} />
          </Typography>
          <Typography>{issuance.issuer_name}</Typography>
          <RecordStatus status={issuance.status} />
          <Button component={Link} to={`/records/issuances/${issuance.id}`}>
            {t('service.official')}
          </Button>
        </Stack>
      ))}
      <Pagination
        count={query.data?.meta?.last_page ?? 1}
        page={page}
        onChange={(_, value) => setPage(value)}
      />
      {query.isPending && <Skeleton variant="rectangular" height={96} aria-label={t('loading')} />}
      {!query.isPending && !query.isError && query.data?.data?.length === 0 && (
        <Alert severity="info" icon={<InboxOutlined />}>
          {t('service.noCopies')}
        </Alert>
      )}
      {(error || query.isError) && (
        <Alert
          severity="error"
          action={
            <Button
              onClick={() => {
                setError(false)
                void query.refetch()
              }}
            >
              {t('retry')}
            </Button>
          }
        >
          {t('loadError')}
        </Alert>
      )}
    </Stack>
  )
}
