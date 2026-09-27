import { Alert, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import {
  useConfigureTranscriptFee,
  useTranscriptFeeConfiguration,
} from '../../shared/api/generated/endpoints'
import { FeeForm } from './FeeForm'

export function FeePage() {
  const { t } = useTranslation('records')
  const query = useTranscriptFeeConfiguration()
  const mutation = useConfigureTranscriptFee()
  return (
    <>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('fee.title')}
      </Typography>
      {query.isPending && <Typography>{t('loading')}</Typography>}
      {query.isError && <Alert severity="error">{t('loadError')}</Alert>}
      {query.data?.data && (
        <FeeForm
          config={query.data.data}
          save={async (data) => {
            await mutation.mutateAsync({ data })
            await query.refetch()
          }}
        />
      )}
    </>
  )
}
