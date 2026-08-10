import { useState } from 'react'
import { useSaveOneGradeSheetLine, useSignTheSheet } from '../../../shared/api/generated/endpoints'
import { apiErrorMessage, apiFieldErrors } from '../../../shared/api/errors'
import { parseMark } from '../letterGrade'
import type { GradeSheetLine } from './GradeSheetTable'

interface UseGradeSheetEditorArgs {
  /** The round being edited — `undefined` while still loading. */
  sheetId: number | undefined
  lines: GradeSheetLine[]
  /**
   * Whether a line still counts as blank for the sign guard — an Initial round's Incomplete
   * declaration is decided, not blank (ticket 05), while an IncompleteResolution round's one
   * line is never itself Incomplete-declared, so the two callers differ here.
   */
  isLineBlank: (line: GradeSheetLine) => boolean
  /** Refetch after a mark saves. */
  onSaved: () => Promise<unknown>
  /** Refetch (and invalidate whatever list surfaced this Sheet) after signing. */
  onSigned: () => Promise<unknown>
  saveErrorFallback: string
  signErrorFallback: string
}

/**
 * The mark-entry state machine shared by {@see GradeSheetPage} (an Offering's Initial round)
 * and {@see IncompleteResolutionPage} (one Incomplete's resolution round) — the two differ only
 * in where their Sheet comes from and in whether a line may also be declared Incomplete
 * (ticket 05), never in how a mark is drafted, saved on blur, or how the round is signed.
 *
 * The page still owns the drafts/errors it wants to add to (an Incomplete declaration shares
 * the same `errors`/`savingEnrollmentId` state via the setters this hook exposes), and the
 * mutation itself, so this hook never becomes a place a caller reaches around.
 */
export function useGradeSheetEditor({
  sheetId,
  lines,
  isLineBlank,
  onSaved,
  onSigned,
  saveErrorFallback,
  signErrorFallback,
}: UseGradeSheetEditorArgs) {
  const saveLine = useSaveOneGradeSheetLine()
  const sign = useSignTheSheet()

  // What is currently typed in each row, keyed by enrollment id. Seeded from the server's
  // recorded marks the first time the Sheet loads, then owned here — so a row the Instructor
  // is mid-edit is never overwritten by a refetch.
  const [drafts, setDrafts] = useState<Record<number, string> | null>(null)
  const [errors, setErrors] = useState<Record<number, string>>({})
  const [savingEnrollmentId, setSavingEnrollmentId] = useState<number | null>(null)
  const [signError, setSignError] = useState<string | null>(null)

  const seeded =
    drafts ??
    Object.fromEntries(
      lines.map((line) => [line.enrollment_id, line.grade?.mark?.toString() ?? '']),
    )

  const blankCount = lines.filter(isLineBlank).length

  const setDraft = (enrollmentId: number, value: string) => {
    setDrafts({ ...seeded, [enrollmentId]: value })
    // A fresh keystroke clears the row's stale server error — the message described the
    // previous value, not this one.
    setErrors((prev) => {
      if (prev[enrollmentId] === undefined) return prev
      const next = { ...prev }
      delete next[enrollmentId]
      return next
    })
  }

  /**
   * Commit one row on blur. A row is only sent when the typed value is a mark the server would
   * accept and it actually differs from what is recorded — so tabbing through an already-graded
   * roster writes nothing, and a half-typed or cleared field is left alone rather than sent to
   * be rejected. (Clearing a recorded mark is not a delete: there is no way to unrecord a Grade,
   * and inventing one here would be scope neither Sheet's lifecycle has settled.)
   */
  const commit = async (enrollmentId: number) => {
    if (sheetId === undefined) return

    const line = lines.find((candidate) => candidate.enrollment_id === enrollmentId)
    const mark = parseMark(seeded[enrollmentId] ?? '')
    if (line === undefined || mark === null || mark === line.grade?.mark) return

    setSavingEnrollmentId(enrollmentId)
    try {
      await saveLine.mutateAsync({ gradeSheetId: sheetId, enrollmentId, data: { mark } })
      await onSaved()
    } catch (saveError) {
      // The server's own message, already localised by Accept-Language — the field message
      // when it is a 422 on `mark`, the envelope message otherwise (403 / 404 / 409).
      const message =
        apiFieldErrors(saveError).mark?.[0] ?? apiErrorMessage(saveError) ?? saveErrorFallback
      setErrors((prev) => ({ ...prev, [enrollmentId]: message }))
    } finally {
      setSavingEnrollmentId(null)
    }
  }

  /**
   * Sign the Sheet as one act (SRS §4.7.1.4). The blank-line count already rendered below the
   * button is the same guard the server enforces, so this is a defence against a stale client —
   * not the Instructor's first signal that lines remain. A 422 here still surfaces the server's
   * message, in case the roster changed since the page loaded.
   */
  const handleSign = async () => {
    if (sheetId === undefined) return

    setSignError(null)
    try {
      await sign.mutateAsync({ gradeSheetId: sheetId })
      await onSigned()
    } catch (signingError) {
      setSignError(
        apiFieldErrors(signingError).lines?.[0] ??
          apiErrorMessage(signingError) ??
          signErrorFallback,
      )
    }
  }

  return {
    seeded,
    errors,
    setErrors,
    savingEnrollmentId,
    setSavingEnrollmentId,
    signError,
    blankCount,
    setDraft,
    commit,
    handleSign,
    signPending: sign.isPending,
  }
}
