import { useState } from 'react'
import {
  Alert,
  Box,
  CircularProgress,
  Paper,
  Step,
  StepButton,
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
import { StatusView } from '../StatusView'
import { ReturnedNotice } from '../ReturnedNotice'

/** The post-verification sub-steps the applicant walks client-side (the server
 *  status stays `email_verified` across all of them until finalize). `personal` is
 *  reached only by going Back from Documents to correct the snapshot (Phase 2.5 B3);
 *  the forward path is documents → program → review. */
type PostVerifyStep = 'personal' | 'documents' | 'program' | 'review'

/** Which Stepper node a post-verify sub-step lights up. `personal` maps back to the
 *  first node so Back-navigation reads correctly on the shared stepper. */
const POST_VERIFY_STEP_INDEX: Record<PostVerifyStep, number> = {
  personal: WIZARD_STEP.personalInfo,
  documents: WIZARD_STEP.documents,
  program: WIZARD_STEP.program,
  review: WIZARD_STEP.review,
}

/** The reverse map: which post-verify sub-step a clickable Stepper node jumps to. The
 *  email-verification node is deliberately absent — it is already done and cannot be
 *  revisited, so it stays non-clickable (ticket 08). */
const STEP_INDEX_TO_POST_VERIFY: Partial<Record<number, PostVerifyStep>> = {
  [WIZARD_STEP.personalInfo]: 'personal',
  [WIZARD_STEP.documents]: 'documents',
  [WIZARD_STEP.program]: 'program',
  [WIZARD_STEP.review]: 'review',
}

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
  // When the applicant edits one section from the Review step, we remember to bring
  // them straight back to Review once they leave that step — so an Edit is a there-and-
  // back jump, not a detour that re-walks the whole tail of the wizard (ticket 08).
  const [returnToReview, setReturnToReview] = useState(false)

  // Free navigation (a Stepper click): go to the step, cancelling any pending
  // return-to-Review from an in-progress section edit.
  const goToStep = (step: PostVerifyStep) => {
    setReturnToReview(false)
    setPostVerifyStep(step)
  }

  // Edit one section from Review: jump to its step and arm the return, so the step's
  // next Back/Continue lands back on Review.
  const editFromReview = (step: PostVerifyStep) => {
    setReturnToReview(true)
    setPostVerifyStep(step)
  }

  // Leaving a step by its own Back/Continue: return to Review if we came from there to
  // edit, otherwise follow the wizard's normal linear neighbour.
  const leaveStep = (fallback: PostVerifyStep) => {
    if (returnToReview) {
      setReturnToReview(false)
      setPostVerifyStep('review')
    } else {
      setPostVerifyStep(fallback)
    }
  }

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
  // A returned application is back in the applicant's hands: it re-walks the
  // post-verify sub-wizard (documents → program → review) and resubmits, rather than
  // showing the read-only status view that every other post-finalize status does.
  const returned = status === 'returned_for_revision'
  const inReview = isFinalized(status) && !returned
  const verified = status === 'email_verified'
  const editing = verified || returned

  // Which Stepper node reads as active: an in-review application reads as done; the
  // post-verify / resubmit phase tracks its local sub-step; everything earlier
  // follows the shared status→step mapping (stepForStatus).
  const activeStep = inReview
    ? WIZARD_STEP_KEYS.length
    : editing
      ? POST_VERIFY_STEP_INDEX[postVerifyStep]
      : stepForStatus(status)

  return (
    <Box sx={{ py: 2 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('wizard.title')}
      </Typography>

      <Stepper activeStep={activeStep} alternativeLabel nonLinear={editing} sx={{ my: 3 }}>
        {WIZARD_STEP_KEYS.map((key, index) => {
          // In the post-verify phase the applicant may jump between the reachable steps
          // (personal info, documents, program, review), skipping the already-done email
          // step; earlier phases keep the read-only linear stepper.
          const target = STEP_INDEX_TO_POST_VERIFY[index]
          const clickable = editing && target !== undefined
          return (
            <Step key={key}>
              {clickable ? (
                <StepButton onClick={() => goToStep(target)}>{t(`wizard.steps.${key}`)}</StepButton>
              ) : (
                <StepLabel>{t(`wizard.steps.${key}`)}</StepLabel>
              )}
            </Step>
          )
        })}
      </Stepper>

      <Paper variant="outlined" sx={{ p: { xs: 2, sm: 3 } }}>
        {inReview ? (
          <StatusView applicationId={application.id ?? 0} status={status} />
        ) : editing ? (
          <Box>
            {returned ? (
              <ReturnedNotice applicationId={application.id ?? 0} />
            ) : (
              <Alert severity="success" sx={{ mb: 2 }}>
                {t('verify.verified')}
              </Alert>
            )}
            {postVerifyStep === 'personal' && (
              <PersonalInfoStep application={application} onBack={() => leaveStep('documents')} />
            )}
            {postVerifyStep === 'documents' && (
              <DocumentsStep
                application={application}
                onBack={() => leaveStep('personal')}
                onContinue={() => leaveStep('program')}
              />
            )}
            {postVerifyStep === 'program' && (
              <ProgramStep
                application={application}
                onBack={() => leaveStep('documents')}
                onContinue={() => leaveStep('review')}
              />
            )}
            {postVerifyStep === 'review' && (
              <ReviewStep
                application={application}
                mode={returned ? 'resubmit' : 'finalize'}
                onBack={() => setPostVerifyStep('program')}
                onEdit={editFromReview}
              />
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
