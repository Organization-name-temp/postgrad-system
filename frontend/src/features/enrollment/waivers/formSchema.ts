import { z } from 'zod'

export const waiverDraftSchema = z
  .object({
    targetId: z.string().min(1, 'required'),
    source: z.enum(['prs', 'legacy_transcript']),
    prsEnrollmentId: z.string(),
    legacyCourseId: z.string(),
    legacyGrade: z.enum(['', 'A+', 'A', 'B+', 'B', 'C+']),
    transcriptDocumentIds: z.array(z.string()),
    note: z.string().max(2000, 'too_long'),
  })
  .superRefine((values, context) => {
    if (values.source === 'prs' && !values.prsEnrollmentId) {
      context.addIssue({ code: 'custom', path: ['prsEnrollmentId'], message: 'required' })
    }
    if (values.source === 'legacy_transcript') {
      if (!values.legacyCourseId) {
        context.addIssue({ code: 'custom', path: ['legacyCourseId'], message: 'required' })
      }
      if (!values.legacyGrade) {
        context.addIssue({ code: 'custom', path: ['legacyGrade'], message: 'required' })
      }
      if (values.transcriptDocumentIds.length === 0) {
        context.addIssue({
          code: 'custom',
          path: ['transcriptDocumentIds'],
          message: 'required',
        })
      }
    }
  })

export const waiverReviewSchema = z.object({
  reason: z.string().max(2000, 'too_long'),
  coverage: z.string(),
  equivalencyNote: z.string().max(2000, 'too_long'),
})

export const headVerificationSchema = waiverReviewSchema.superRefine((values, context) => {
  const coverage = Number(values.coverage)
  if (!Number.isFinite(coverage) || coverage < 80 || coverage > 100) {
    context.addIssue({ code: 'custom', path: ['coverage'], message: 'coverage_minimum' })
  }
  if (!values.equivalencyNote.trim()) {
    context.addIssue({ code: 'custom', path: ['equivalencyNote'], message: 'required' })
  }
})

export type WaiverDraftValues = z.infer<typeof waiverDraftSchema>
export type WaiverReviewValues = z.infer<typeof waiverReviewSchema>
