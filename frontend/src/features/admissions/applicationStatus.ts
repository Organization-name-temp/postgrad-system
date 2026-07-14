import type { StatusChipVariant, StatusTone } from '../../shared/ui/StatusChip'

/**
 * The 13 Application statuses, mirrored from the backend `ApplicationStatus` enum
 * (Modules\Admissions\Internal\Enums\ApplicationStatus). The server is the single
 * source of truth for the workflow; the SPA only renders each status. Kept as a
 * plain union of the wire values (the API sends the snake_case string), colocated
 * with the feature that owns the workflow.
 */
export const APPLICATION_STATUSES = [
  'draft',
  'submitted',
  'email_verified',
  'under_sa_review',
  'sa_approved',
  'returned_for_revision',
  'under_dean_review',
  'interview_scheduled',
  'interview_completed',
  'accepted',
  'conditionally_accepted',
  'waitlisted',
  'rejected',
] as const

export type ApplicationStatus = (typeof APPLICATION_STATUSES)[number]

/** A `<StatusChip>` styling for a status: which tone and which fill treatment. */
export interface StatusChipStyle {
  tone: StatusTone
  variant: StatusChipVariant
}

/**
 * Maps each Application status to its `<StatusChip>` tone + variant. This is the
 * authoritative colocated map the design-system.md §10 "Application status" catalogue
 * describes (Phase 0.5 shipped only static demo data); each feature owns its own
 * status→chip map (the notifications bell keeps its type→alias map beside it). The
 * chip's *label* is a separate i18n concern ({@see ApplicationStatusChip}); this map
 * encodes only the visual meaning.
 *
 * The values follow the §10 table exactly where it lists a status: Draft → neutral
 * outlined; Submitted / Email Verified → `info` soft; the in-progress review states
 * (Under SA/Dean Review, Interview Scheduled) → `secondary` (slate) soft; Accepted →
 * `success` filled; Conditionally Accepted → `warning` filled; Waitlisted → `warning`
 * outlined; Rejected → `error` filled. The three statuses §10 does not tabulate are
 * placed by the same logic: `sa_approved` and `interview_completed` are transient
 * in-progress states (`secondary` soft, with the group); `returned_for_revision`
 * needs applicant action, so it is the one filled `warning` non-terminal.
 */
export const statusToChip: Record<ApplicationStatus, StatusChipStyle> = {
  draft: { tone: 'neutral', variant: 'outlined' },
  submitted: { tone: 'info', variant: 'filled-soft' },
  email_verified: { tone: 'info', variant: 'filled-soft' },
  under_sa_review: { tone: 'secondary', variant: 'filled-soft' },
  sa_approved: { tone: 'secondary', variant: 'filled-soft' },
  returned_for_revision: { tone: 'warning', variant: 'filled' },
  under_dean_review: { tone: 'secondary', variant: 'filled-soft' },
  interview_scheduled: { tone: 'secondary', variant: 'filled-soft' },
  interview_completed: { tone: 'secondary', variant: 'filled-soft' },
  accepted: { tone: 'success', variant: 'filled' },
  conditionally_accepted: { tone: 'warning', variant: 'filled' },
  waitlisted: { tone: 'warning', variant: 'outlined' },
  rejected: { tone: 'error', variant: 'filled' },
}

/**
 * The happy-path milestones the progress indicator walks, in order — the coarse
 * stages an applicant recognises, not all 13 statuses. Branch and terminal states
 * map onto the stage they belong to via {@see stageForStatus}.
 */
export const PROGRESS_STAGES = [
  'submitted',
  'saReview',
  'deanReview',
  'interview',
  'decision',
] as const

export type ProgressStage = (typeof PROGRESS_STAGES)[number]

/**
 * How far along the happy path a status sits — the 0-based index into
 * {@see PROGRESS_STAGES} the progress indicator fills up to. A `returned_for_revision`
 * application has bounced back at the SA-review stage; every decision outcome
 * (including `rejected`) sits at the final "decision" stage.
 */
export function stageForStatus(status: ApplicationStatus): number {
  switch (status) {
    case 'draft':
    case 'submitted':
    case 'email_verified':
      return 0
    case 'under_sa_review':
    case 'sa_approved':
    case 'returned_for_revision':
      return 1
    case 'under_dean_review':
      return 2
    case 'interview_scheduled':
    case 'interview_completed':
      return 3
    case 'accepted':
    case 'conditionally_accepted':
    case 'waitlisted':
    case 'rejected':
      return 4
  }
}

/** A wire status string narrowed to a known {@see ApplicationStatus}, or null. */
export function toApplicationStatus(value: string | undefined): ApplicationStatus | null {
  return value !== undefined && (APPLICATION_STATUSES as readonly string[]).includes(value)
    ? (value as ApplicationStatus)
    : null
}
