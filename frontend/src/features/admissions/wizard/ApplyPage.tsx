import { useState } from 'react'
import {
  Alert,
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
import { WIZARD_STEP, WIZARD_STEP_KEYS, stepForStatus } from './wizardSteps'

/**
 * The applicant's multi-step application wizard. The server's Application status
 * drives which step is active (so a reload resumes in place); ticket 04 builds
 * Step 1 (personal info) and Step 2 (email verification), with documents, program
 * selection, and review shown as upcoming. The verification link issued on submit
 * is held here and handed to the verify step.
 */
export function ApplyPage() {
  const { t } = useTranslation('admissions')
  const { data, isPending, isError } = useMyApplication()
  const application = data?.data
  const [verificationUrl, setVerificationUrl] = useState<string | null>(null)

  const activeStep = stepForStatus(application?.status)

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
        {activeStep === WIZARD_STEP.personalInfo && (
          <PersonalInfoStep application={application} onSubmitted={setVerificationUrl} />
        )}
        {activeStep === WIZARD_STEP.verifyEmail && (
          <EmailVerifyStep verificationUrl={verificationUrl} onResent={setVerificationUrl} />
        )}
        {activeStep >= WIZARD_STEP.documents && (
          <Box>
            <Alert severity="success" sx={{ mb: 2 }}>
              {t('verify.verified')}
            </Alert>
            <DocumentsStep application={application} />
          </Box>
        )}
      </Paper>
    </Box>
  )
}
