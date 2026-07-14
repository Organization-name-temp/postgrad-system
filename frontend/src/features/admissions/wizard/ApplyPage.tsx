import { useState } from 'react'
import {
  Alert,
  AlertTitle,
  Box,
  CircularProgress,
  Paper,
  Step,
  StepLabel,
  Stepper,
  Typography,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useMyApplication } from '../../../shared/api/generated/endpoints'
import { PersonalInfoStep } from './PersonalInfoStep'
import { EmailVerifyStep } from './EmailVerifyStep'
import { DocumentsStep } from './DocumentsStep'
import { ProgramStep } from './ProgramStep'
import { ReviewStep } from './ReviewStep'
import { WIZARD_STEP, WIZARD_STEP_KEYS, isFinalized, stepForStatus } from './wizardSteps'

/** The post-verification sub-steps the applicant walks client-side (the server
 *  status stays `email_verified` across all three until finalize). */
type PostVerifyStep = 'documents' | 'program' | 'review'

/**
 * The applicant's multi-step application wizard. The server's Application status
 * drives the phase — personal info (`draft`), email verification (`submitted`), and,
 * once verified, the post-verification sub-wizard (documents → program → review)
 * that the SPA navigates locally since the status holds at `email_verified` until
 * finalize. Finalizing moves the application into review, where the wizard shows a
 * read-only confirmation. A reload always resumes in the right phase.
 */
export function ApplyPage() {
  const { t } = useTranslation('admissions')
  const { data, isPending, isError } = useMyApplication()
  const application = data?.data
  const [verificationUrl, setVerificationUrl] = useState<string | null>(null)
  const [postVerifyStep, setPostVerifyStep] = useState<PostVerifyStep>('documents')

  if (isPending) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}>
        <CircularProgress aria-label={t('wizard.loading')} />
      </Box>
    )
  }

  // The only expected error here is 404 — a user without an application (e.g. a
  // staff member who navigated in). Auth failures are handled upstream by the route
  // guard. Either way, there is nothing to fill.
  if (isError || !application) {
    return (
      <Box sx={{ py: 4 }}>
        <Typography variant="h4" component="h1" gutterBottom>
          {t('wizard.title')}
        </Typography>
        <Alert severity="info">{t('wizard.none')}</Alert>
      </Box>
    )
  }

  const status = application.status
  const finalized = isFinalized(status)
  const verified = status === 'email_verified'

  // Which Stepper node reads as active: a finalized application reads as done; the
  // post-verify phase tracks its local sub-step; everything earlier follows the
  // shared status→step mapping (stepForStatus).
  const activeStep = finalized
    ? WIZARD_STEP_KEYS.length
    : verified
      ? WIZARD_STEP[postVerifyStep]
      : stepForStatus(status)

  return (
    <Box sx={{ py: 2 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('wizard.title')}
      </Typography>

      <Stepper activeStep={activeStep} alternativeLabel sx={{ my: 3 }}>
        {WIZARD_STEP_KEYS.map((key) => (
          <Step key={key}>
            <StepLabel>{t(`wizard.steps.${key}`)}</StepLabel>
          </Step>
        ))}
      </Stepper>

      <Paper variant="outlined" sx={{ p: { xs: 2, sm: 3 } }}>
        {finalized ? (
          <Alert severity="success">
            <AlertTitle>{t('wizard.underReviewTitle')}</AlertTitle>
            {t('wizard.underReviewBody')}
          </Alert>
        ) : verified ? (
          <Box>
            <Alert severity="success" sx={{ mb: 2 }}>
              {t('verify.verified')}
            </Alert>
            {postVerifyStep === 'documents' && (
              <DocumentsStep
                application={application}
                onContinue={() => setPostVerifyStep('program')}
              />
            )}
            {postVerifyStep === 'program' && (
              <ProgramStep
                application={application}
                onBack={() => setPostVerifyStep('documents')}
                onContinue={() => setPostVerifyStep('review')}
              />
            )}
            {postVerifyStep === 'review' && (
              <ReviewStep application={application} onBack={() => setPostVerifyStep('program')} />
            )}
          </Box>
        ) : status === 'submitted' ? (
          <EmailVerifyStep verificationUrl={verificationUrl} onResent={setVerificationUrl} />
        ) : (
          <PersonalInfoStep application={application} onSubmitted={setVerificationUrl} />
        )}
      </Paper>
    </Box>
  )
}
