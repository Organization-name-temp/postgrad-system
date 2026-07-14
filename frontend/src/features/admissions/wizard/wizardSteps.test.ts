import { describe, expect, it } from 'vitest'
import { WIZARD_STEP, stepForStatus } from './wizardSteps'

/*
| The wizard resumes at the step implied by the server's Application status, so a
| reload lands the applicant where they left off. This pins that mapping.
*/
describe('stepForStatus', () => {
  it('starts a draft on the personal-info step', () => {
    expect(stepForStatus('draft')).toBe(WIZARD_STEP.personalInfo)
  })

  it('sends a submitted application to the email-verify step', () => {
    expect(stepForStatus('submitted')).toBe(WIZARD_STEP.verifyEmail)
  })

  it('advances past verification once the email is verified', () => {
    expect(stepForStatus('email_verified')).toBe(WIZARD_STEP.documents)
  })

  it('treats any later workflow status as past the ticket-04 steps', () => {
    expect(stepForStatus('under_sa_review')).toBe(WIZARD_STEP.documents)
  })

  it('defaults to the first step when the status is unknown', () => {
    expect(stepForStatus(undefined)).toBe(WIZARD_STEP.personalInfo)
  })
})
