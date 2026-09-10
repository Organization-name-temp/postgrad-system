import ErrorOutlineIcon from '@mui/icons-material/ErrorOutlineRounded'
import { Stack } from '@mui/material'
import { z } from 'zod'

export const transferEvidenceSchema = z
  .object({
    targetId: z.string().optional(),
    institution: z.string().min(1, 'required').max(500, 'too_long'),
    courseCode: z.string().min(1, 'required').max(200, 'too_long'),
    courseName: z.string().min(1, 'required').max(500, 'too_long'),
    sourceHours: z.string().min(1, 'required'),
    printedGrade: z.string().min(1, 'required').max(200, 'too_long'),
    documentIds: z.string().min(1, 'required'),
    note: z.string().max(2000, 'too_long'),
  })
  .superRefine((values, context) => {
    const hours = Number(values.sourceHours)
    if (!Number.isFinite(hours) || hours <= 0 || hours > 999999.99) {
      context.addIssue({ code: 'custom', path: ['sourceHours'], message: 'positive_number' })
    }
    const documents = parseDocumentIds(values.documentIds)
    if (documents.length === 0 || documents.some((id) => !Number.isInteger(id) || id < 1)) {
      context.addIssue({ code: 'custom', path: ['documentIds'], message: 'document_ids' })
    }
  })

export const newTransferSchema = transferEvidenceSchema.refine(
  (values) => Boolean(values.targetId),
  {
    path: ['targetId'],
    message: 'required',
  },
)

export const transferReviewSchema = z.object({
  reason: z.string().max(2000, 'too_long'),
  normalizedGrade: z.enum(['', 'A+', 'A', 'B+', 'B']),
})

export type TransferEvidenceValues = z.infer<typeof transferEvidenceSchema>
export type TransferReviewValues = z.infer<typeof transferReviewSchema>

export function parseDocumentIds(value: string): number[] {
  return value
    .split(',')
    .map((item) => Number(item.trim()))
    .filter((item, index, all) => Number.isFinite(item) && all.indexOf(item) === index)
}

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
