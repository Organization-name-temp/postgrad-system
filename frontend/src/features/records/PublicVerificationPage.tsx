import { transcriptPrintSx } from './transcriptPrint'
import { useParams } from 'react-router-dom'
import { Alert, Box, Button, Container, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { usePublicBearerVerificationOfAnOfficialCopy } from '../../shared/api/generated/endpoints'
import { IssuedDocument } from './IssuedDocument'

export function PublicVerificationPage() {
  const { token = '' } = useParams()
  const { t, i18n } = useTranslation('records')
  const query = usePublicBearerVerificationOfAnOfficialCopy(token, {
    query: { refetchOnWindowFocus: 'always', staleTime: 0, gcTime: 0, retry: false },
  })
  return (
    <Container component="main" maxWidth="lg" sx={[{ py: 3 }, transcriptPrintSx]}>
      <Box sx={{ '@media print': { display: 'none' }, mb: 2 }}>
        <Stack direction="row" spacing={2}>
          <Button onClick={() => void i18n.changeLanguage(i18n.language === 'ar' ? 'en' : 'ar')}>
            {i18n.language === 'ar' ? 'English' : 'العربية'}
          </Button>
          <Button onClick={() => void query.refetch()}>{t('service.refresh')}</Button>
          <Button
            disabled={!query.data?.data || query.isFetching || query.isError}
            onClick={() => window.print()}
          >
            {t('print')}
          </Button>
        </Stack>
      </Box>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('service.verify')}
      </Typography>
      {query.isFetching ? (
        <Typography>{t('loading')}</Typography>
      ) : query.isError || !query.data?.data ? (
        <Alert severity="error">{t('service.publicError')}</Alert>
      ) : (
        <IssuedDocument
          issuance={query.data.data}
          verificationPath={`/records/verify/${token}`}
          publicView
        />
      )}
    </Container>
  )
}
