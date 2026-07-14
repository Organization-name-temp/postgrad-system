import { useState } from 'react'
import { Alert, Box, Button, Link, Paper, Stack, Typography } from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getMyApplicationQueryKey,
  useResendTheVerificationLink,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { http } from '../../../shared/api/http'

interface EmailVerifyStepProps {
  /** The current verification link (from submit or the last resend), or null if none is held — e.g. after a reload, when the applicant must resend to get a fresh one. */
  verificationUrl: string | null
  /** Called with a freshly-issued link after a successful resend. */
  onResent: (verificationUrl: string) => void
}

/**
 * Step 2 of the wizard: email verification. In this build no mail is sent
 * (ADR-0005), so the signed link is shown on screen for transparency; the
 * applicant confirms it in-app with "Verify email" (the SPA follows the signed
 * link), which the server turns into `email_verified` and the wizard then
 * advances past. A missed link can be re-requested, throttled server-side (BR-72).
 */
export function EmailVerifyStep({ verificationUrl, onResent }: EmailVerifyStepProps) {
  const { t } = useTranslation('admissions')
  const queryClient = useQueryClient()

  const [verifying, setVerifying] = useState(false)
  const [verifyFailed, setVerifyFailed] = useState(false)
  const resend = useResendTheVerificationLink()

  const handleVerify = async () => {
    if (!verificationUrl) {
      return
    }
    setVerifying(true)
    setVerifyFailed(false)
    try {
      // Follow the signed link ourselves (same-origin via the dev proxy) so the
      // applicant stays in the SPA; the server marks the email verified and moves
      // the Application on, which the refetched status reflects.
      await http.get(verificationUrl)
      await queryClient.invalidateQueries({ queryKey: getMyApplicationQueryKey() })
    } catch {
      setVerifyFailed(true)
    } finally {
      setVerifying(false)
    }
  }

  const handleResend = () => {
    resend.mutate(undefined, {
      onSuccess: (result) => {
        if (result.verification_url) {
          onResent(result.verification_url)
        }
      },
    })
  }

  const resendError = resend.isError
    ? (apiErrorMessage(resend.error) ?? t('verify.resendError'))
    : undefined

  return (
    <Box>
      <Typography variant="h6" gutterBottom>
        {t('verify.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('verify.intro')}
      </Typography>

      {verifyFailed && (
        <Alert severity="error" sx={{ my: 2 }}>
          {t('verify.verifyError')}
        </Alert>
      )}
      {resendError && (
        <Alert severity="error" sx={{ my: 2 }}>
          {resendError}
        </Alert>
      )}

      {verificationUrl ? (
        <Paper variant="outlined" sx={{ p: 2, my: 2 }}>
          <Typography variant="caption" color="text.secondary" component="p" gutterBottom>
            {t('verify.linkLabel')}
          </Typography>
          <Link href={verificationUrl} sx={{ wordBreak: 'break-all' }}>
            {verificationUrl}
          </Link>
        </Paper>
      ) : (
        <Alert severity="info" sx={{ my: 2 }}>
          {t('verify.noLink')}
        </Alert>
      )}

      <Stack direction="row" spacing={2} sx={{ mt: 2 }}>
        <Button variant="contained" onClick={handleVerify} disabled={!verificationUrl || verifying}>
          {verifying ? t('verify.verifying') : t('verify.verify')}
        </Button>
        <Button variant="outlined" onClick={handleResend} disabled={resend.isPending}>
          {resend.isPending ? t('verify.resending') : t('verify.resend')}
        </Button>
      </Stack>
    </Box>
  )
}
