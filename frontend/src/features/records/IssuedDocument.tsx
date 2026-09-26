import { transcriptPrintSx } from './transcriptPrint'
import { Alert, Box, Link, Stack, Typography, useTheme } from '@mui/material'
import { QRCodeSVG } from 'qrcode.react'
import { useTranslation } from 'react-i18next'
import { TranscriptDocument, type TranscriptDocumentRecord } from './TranscriptDocument'

interface IssuanceDisplay {
  reference?: string
  status?: string
  issuer_name?: string
  issued_at?: string
  snapshot?: TranscriptDocumentRecord
}
export function IssuedDocument({
  issuance,
  verificationPath,
  publicView = false,
}: {
  issuance: IssuanceDisplay
  verificationPath: string
  publicView?: boolean
}) {
  const { t, i18n } = useTranslation('records')
  const theme = useTheme()
  const revoked = issuance.status === 'Revoked'
  const url = new URL(verificationPath, window.location.origin).href
  return (
    <Stack spacing={2} sx={transcriptPrintSx} dir={i18n.language === 'ar' ? 'rtl' : 'ltr'}>
      <Alert severity={revoked ? 'warning' : 'success'}>
        {t(revoked ? 'service.revoked' : 'service.valid')}
      </Alert>
      <Typography>{t('service.referenceLabel')}</Typography>
      <Typography dir="ltr" sx={{ unicodeBidi: 'isolate', overflowWrap: 'anywhere' }}>
        {issuance.reference}
      </Typography>
      {(!revoked || !publicView) && (
        <>
          <Typography>
            {t('service.issuer', { name: issuance.issuer_name, date: issuance.issued_at })}
          </Typography>
          {issuance.snapshot && <TranscriptDocument record={issuance.snapshot} official />}
          <Box
            sx={{
              border: 2,
              borderColor: 'primary.main',
              borderRadius: 2,
              p: 2,
              textAlign: 'center',
              breakInside: 'avoid',
            }}
          >
            <Typography variant="h6">{t('service.seal')}</Typography>
            <Typography variant="caption">{t('service.attribution')}</Typography>
          </Box>
          <Box sx={{ breakInside: 'avoid' }}>
            <QRCodeSVG
              value={url}
              title={t('service.qr')}
              marginSize={4}
              level="M"
              bgColor={theme.palette.common.white}
              fgColor={theme.palette.common.black}
            />
            <Link
              href={url}
              referrerPolicy="no-referrer"
              sx={{ display: 'block', overflowWrap: 'anywhere' }}
            >
              {t('service.verify')}
            </Link>
          </Box>
        </>
      )}
    </Stack>
  )
}
