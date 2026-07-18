import { describe, expect, it } from 'vitest'
import {
  APPLICATION_STATUSES,
  PROGRESS_STAGES,
  stageForStatus,
  statusToChip,
  toApplicationStatus,
} from './applicationStatus'

describe('statusToChip', () => {
  it('maps every one of the 13 statuses to a chip style', () => {
    expect(APPLICATION_STATUSES).toHaveLength(13)
    for (const status of APPLICATION_STATUSES) {
      expect(statusToChip[status]).toBeDefined()
      expect(statusToChip[status].tone).toBeTruthy()
      expect(statusToChip[status].variant).toBeTruthy()
    }
  })

  it('follows the design-system.md §10 Application-status catalogue', () => {
    // Verbatim from the §10 table.
    expect(statusToChip.draft).toEqual({ tone: 'neutral', variant: 'outlined' })
    expect(statusToChip.submitted).toEqual({ tone: 'info', variant: 'filled-soft' })
    expect(statusToChip.email_verified).toEqual({ tone: 'info', variant: 'filled-soft' })
    // In-progress review states are slate (`secondary`), soft.
    expect(statusToChip.under_sa_review).toEqual({ tone: 'secondary', variant: 'filled-soft' })
    expect(statusToChip.under_dean_review).toEqual({ tone: 'secondary', variant: 'filled-soft' })
    expect(statusToChip.interview_scheduled).toEqual({ tone: 'secondary', variant: 'filled-soft' })
    expect(statusToChip.accepted).toEqual({ tone: 'success', variant: 'filled' })
    expect(statusToChip.conditionally_accepted).toEqual({ tone: 'warning', variant: 'filled' })
    expect(statusToChip.waitlisted).toEqual({ tone: 'warning', variant: 'outlined' })
    expect(statusToChip.rejected).toEqual({ tone: 'error', variant: 'filled' })
  })

  it('places the statuses §10 does not tabulate by the same logic', () => {
    // Transient in-progress states join the slate review group.
    expect(statusToChip.sa_approved).toEqual({ tone: 'secondary', variant: 'filled-soft' })
    expect(statusToChip.interview_completed).toEqual({ tone: 'secondary', variant: 'filled-soft' })
    // Needs applicant action: the one filled `warning` non-terminal.
    expect(statusToChip.returned_for_revision).toEqual({ tone: 'warning', variant: 'filled' })
  })
})

describe('stageForStatus', () => {
  it('places each status on the right progress stage', () => {
    expect(PROGRESS_STAGES).toHaveLength(5)
    expect(stageForStatus('submitted')).toBe(0)
    expect(stageForStatus('under_sa_review')).toBe(1)
    // A returned application has bounced back at the SA-review stage.
    expect(stageForStatus('returned_for_revision')).toBe(1)
    expect(stageForStatus('under_dean_review')).toBe(2)
    expect(stageForStatus('interview_scheduled')).toBe(3)
    // Every decision outcome sits at the final stage.
    expect(stageForStatus('accepted')).toBe(4)
    expect(stageForStatus('waitlisted')).toBe(4)
    expect(stageForStatus('rejected')).toBe(4)
  })
})

describe('toApplicationStatus', () => {
  it('narrows a known wire value and rejects anything else', () => {
    expect(toApplicationStatus('under_sa_review')).toBe('under_sa_review')
    expect(toApplicationStatus('not_a_status')).toBeNull()
    expect(toApplicationStatus(undefined)).toBeNull()
  })
})
