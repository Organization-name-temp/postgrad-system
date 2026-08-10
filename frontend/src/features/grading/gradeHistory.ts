import type { TFunction } from 'i18next'

export interface GradeHistoryEntry {
  mark?: number
  letter_grade?: string
  gpa_points?: number
  finalized_at?: string
  superseded?: boolean
}

/**
 * The generated client still types some `grade_history` shapes loosely, so the UI reads that
 * payload through one narrow parser instead of scattering anonymous casts across pages.
 */
export function readGradeHistory(value: unknown): GradeHistoryEntry[] {
  if (typeof value !== 'object' || value === null || !('grade_history' in value)) {
    return []
  }

  const history = (value as { grade_history?: unknown }).grade_history
  if (!Array.isArray(history)) {
    return []
  }

  return history.map((entry) => {
    const row =
      typeof entry === 'object' && entry !== null
        ? (entry as Partial<Record<keyof GradeHistoryEntry, unknown>>)
        : {}

    return {
      mark: typeof row.mark === 'number' ? row.mark : undefined,
      letter_grade: typeof row.letter_grade === 'string' ? row.letter_grade : undefined,
      gpa_points: typeof row.gpa_points === 'number' ? row.gpa_points : undefined,
      finalized_at: typeof row.finalized_at === 'string' ? row.finalized_at : undefined,
      superseded: row.superseded === true ? true : undefined,
    }
  })
}

export function formatGradeHistory(
  history: GradeHistoryEntry[],
  t: TFunction<'grading'>,
): string | null {
  if (history.length === 0) {
    return null
  }

  return history
    .map((entry) => {
      const letter = entry.letter_grade ?? t('sheet.noLetter')
      const mark = typeof entry.mark === 'number' ? entry.mark.toFixed(2) : t('sheet.noLetter')
      const superseded = entry.superseded ? `, ${t('sheet.historySuperseded')}` : ''

      return `${letter} (${mark}%${superseded})`
    })
    .join(', ')
}
