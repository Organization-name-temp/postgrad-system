import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Alert, Button, MenuItem, Pagination, Stack, TextField, Typography } from '@mui/material'
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
            {issuance.reference} · {issuance.issued_at}
          </Typography>
          <Typography>
            {t(`service.statuses.${issuance.status}`)} · {issuance.issuer_name}
          </Typography>
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
      {query.isPending && <Typography>{t('loading')}</Typography>}
      {(error || query.isError) && <Alert severity="error">{t('loadError')}</Alert>}
    </Stack>
  )
}
