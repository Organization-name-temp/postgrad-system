import { useMemo } from 'react'
import { Box } from '@mui/material'
import { zodResolver } from '@hookform/resolvers/zod'
import { useFieldArray, useForm } from 'react-hook-form'
import { useSubmitARegistrationCart } from '../../../shared/api/generated/endpoints'
import { CartPanel } from './CartPanel'
import { ClassificationSection, type CartControls } from './ClassificationSection'
import { groupByClassification, type EligibleCourse } from './classification'
import {
  buildCartSchema,
  creditHourTotal,
  evaluateCart,
  indexOfferings,
  type CartFormValues,
  type CartMode,
} from './cartRules'

interface CartBuilderProps {
  courses: EligibleCourse[]
  /** Whether the open semester is the student's first — waives the CH floor (mirror). */
  firstSemester: boolean
  /** Called after a successful submit, so the page re-reads the now-Pending request. */
  onSubmitted: () => void
}

/**
 * The cart-builder half of the registration page: the eligible list (grouped by
 * classification) with an add/remove action per row, and the cart panel beside it. The
 * cart is a React Hook Form field array validated by a Zod mirror of the server rules
 * (ADR-0014); the same pure {@link evaluateCart} drives both the submit gate and the live
 * issue messages, so they never disagree. The server re-validates and remains authoritative.
 */
export function CartBuilder({ courses, firstSemester, onSubmitted }: CartBuilderProps) {
  const offerings = useMemo(() => indexOfferings(courses), [courses])
  const schema = useMemo(
    () => buildCartSchema(offerings, { firstSemester }),
    [offerings, firstSemester],
  )

  const { control, handleSubmit, watch } = useForm<CartFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { lines: [] },
    mode: 'onChange',
  })
  const { fields, append, remove, update } = useFieldArray({ control, name: 'lines' })

  const lines = watch('lines')
  const issues = useMemo(
    () => evaluateCart(lines, offerings, { firstSemester }),
    [lines, offerings, firstSemester],
  )
  const total = creditHourTotal(lines, offerings)

  const submit = useSubmitARegistrationCart()

  const indexOf = (offeringId: number) =>
    fields.findIndex((field) => field.offering_id === offeringId)

  const cart: CartControls = {
    has: (offeringId) => indexOf(offeringId) >= 0,
    toggle: (course) => {
      const offeringId = course.offering_id as number
      const index = indexOf(offeringId)
      if (index >= 0) {
        remove(index)
      } else {
        append({ offering_id: offeringId, mode: 'Regular' })
      }
    },
  }

  const setMode = (offeringId: number, mode: CartMode) => {
    const index = indexOf(offeringId)
    if (index >= 0) {
      update(index, { offering_id: offeringId, mode })
    }
  }

  const removeOffering = (offeringId: number) => {
    const index = indexOf(offeringId)
    if (index >= 0) {
      remove(index)
    }
  }

  const onSubmit = handleSubmit(async (values) => {
    await submit.mutateAsync({ data: { lines: values.lines } })
    // The page owns the requests query — re-read it so the now-Pending request shows.
    onSubmitted()
  })

  const groups = groupByClassification(courses)

  return (
    <Box
      sx={{ display: 'grid', gap: 3, gridTemplateColumns: { md: '2fr 1fr' }, alignItems: 'start' }}
    >
      <Box sx={{ minWidth: 0 }}>
        {groups.map((group) => (
          <ClassificationSection key={group.classification} group={group} cart={cart} />
        ))}
      </Box>
      <CartPanel
        lines={lines}
        offerings={offerings}
        issues={issues}
        total={total}
        onSetMode={setMode}
        onRemove={removeOffering}
        onSubmit={onSubmit}
        isSubmitting={submit.isPending}
        submitError={submit.error}
      />
    </Box>
  )
}
