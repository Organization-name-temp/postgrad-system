import ErrorOutlineIcon from '@mui/icons-material/ErrorOutlineRounded'
import { Stack } from '@mui/material'
import { z } from 'zod'

export const evidenceSchema = z
  .object({
    targetId: z.string().optional(),
    basis: z.enum(['english', 'ejust_bsc', 'ejust_msc']),
    englishTest: z.enum(['IELTS', 'TOEFL iBT']),
    score: z.string(),
    previousProgramId: z.string(),
    note: z.string().max(2000, { message: 'too_long' }),
  })
  .superRefine((values, context) => {
    if (values.basis === 'english') {
      const score = Number(values.score)
      if (values.score.trim() === '' || !Number.isFinite(score)) {
        context.addIssue({ code: 'custom', path: ['score'], message: 'required' })
      } else if (score < 0 || score > (values.englishTest === 'IELTS' ? 9 : 120)) {
        context.addIssue({ code: 'custom', path: ['score'], message: 'invalid_range' })
      }
    }
    if (values.basis === 'ejust_msc' && !values.previousProgramId) {
      context.addIssue({ code: 'custom', path: ['previousProgramId'], message: 'required' })
    }
  })

export const newDraftSchema = evidenceSchema.refine((values) => Boolean(values.targetId), {
  path: ['targetId'],
  message: 'required',
})

export const reviewReasonSchema = z.object({
  reason: z.string().max(2000, { message: 'too_long' }),
})

export type EvidenceFormValues = z.infer<typeof evidenceSchema>
export type ReviewReasonValues = z.infer<typeof reviewReasonSchema>

export function FormErrorText({ message }: { message?: string }) {
  if (!message) return undefined
  return (
    <Stack
      component="span"
      role="alert"
      aria-live="polite"
      direction="row"
      spacing={0.5}
      sx={{ alignItems: 'center' }}
    >
      <ErrorOutlineIcon aria-hidden="true" fontSize="inherit" />
      <span>{message}</span>
    </Stack>
  )
}
