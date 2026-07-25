import { describe, expect, it, vi } from 'vitest'
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { GradeSheetTable } from './GradeSheetTable'
import type { GradeSheetLine } from './GradeSheetTable'

// StatusChip's soft variant reads CSS-variable palette entries, so render under a
// cssVariables theme (as the app always does — the StatusChip test precedent).
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

const lines: GradeSheetLine[] = [
  {
    enrollment_id: 5,
    course_status: 'Regular',
    credit_hours: 3,
    student: { name: 'Sara Student', university_id: '2026-MSC-CE-0001' },
    grade: { mark: 82.5, letter_grade: 'B', gpa_points: 3.0 },
  },
  {
    enrollment_id: 6,
    course_status: 'Retake',
    credit_hours: 3,
    student: { name: 'Omar Student', university_id: '2026-MSC-CE-0002' },
    grade: null,
  },
]

function renderTable(overrides: Partial<Parameters<typeof GradeSheetTable>[0]> = {}) {
  const onChange = vi.fn()
  const onCommit = vi.fn()
  render(
    <ThemeProvider theme={theme}>
      <GradeSheetTable
        lines={lines}
        drafts={{ 5: '82.5', 6: '' }}
        savingEnrollmentId={null}
        errors={{}}
        onChange={onChange}
        onCommit={onCommit}
        {...overrides}
      />
    </ThemeProvider>,
  )
  return { onChange, onCommit }
}

describe('GradeSheetTable', () => {
  it('names every mark input after its own student, so the fields are distinguishable', () => {
    renderTable()

    // The non-trivial a11y behaviour: a column header alone would leave N identically-named
    // number fields.
    expect(screen.getByRole('spinbutton', { name: /mark for sara student/i })).toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: /mark for omar student/i })).toBeInTheDocument()
  })

  it('previews the letter and points live from what is typed, not from what is saved', () => {
    // Sara's saved grade is B (82.5); the draft says 95, so the preview must follow the draft.
    renderTable({ drafts: { 5: '95', 6: '' } })

    expect(screen.getByText('A+')).toBeInTheDocument()
    expect(screen.getByText('4.00 points')).toBeInTheDocument()
    expect(screen.queryByText('B')).not.toBeInTheDocument()
  })

  it('shows a dash rather than a letter for a line with no mark typed or recorded', () => {
    renderTable()

    // Omar is ungraded and his field is empty → no letter at all, never an F.
    expect(screen.getByText('—')).toBeInTheDocument()
    expect(screen.queryByText('F')).not.toBeInTheDocument()
  })

  it('falls back to the recorded grade when a field is cleared — clearing is not a delete', () => {
    renderTable({ drafts: { 5: '', 6: '' } })

    // Sara's Grade is still B on the record, and her row still says Recorded, so the letter
    // column must agree rather than showing a dash the chip contradicts.
    expect(screen.getByText('B')).toBeInTheDocument()
    expect(screen.getByText('Recorded')).toBeInTheDocument()
    // Omar has nothing recorded and nothing typed → a dash.
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('flags a mark the server would reject and previews no letter for it', () => {
    renderTable({ drafts: { 5: '82.5', 6: '120' } })

    expect(screen.getByText(/between 0 and 100/i)).toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: /mark for omar student/i })).toHaveAttribute(
      'aria-invalid',
      'true',
    )
    // Omar has no recorded Grade to fall back to, so the rejected figure previews nothing.
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('does not flag an empty field — an ungraded line is a legitimate state', () => {
    renderTable()

    expect(screen.getByRole('spinbutton', { name: /mark for omar student/i })).toHaveAttribute(
      'aria-invalid',
      'false',
    )
  })

  it('emphasises the letter by its grade group (design-system.md §10)', () => {
    // Earned (>= C+) reads as ordinary text; below C+ carries the sub-earn warning; F is an
    // error. Colour is secondary — the letter itself is always present either way.
    renderTable({ drafts: { 5: '95', 6: '62' } })
    expect(screen.getByText('A+')).toHaveStyle({ color: 'var(--mui-palette-text-primary)' })
    expect(screen.getByText('D')).toHaveStyle({ color: 'var(--mui-palette-warning-dark)' })

    cleanup()
    renderTable({ drafts: { 5: '40', 6: '' } })
    expect(screen.getByText('F')).toHaveStyle({ color: 'var(--mui-palette-error-dark)' })
  })

  it('marks each line recorded or not yet graded', () => {
    renderTable()

    expect(screen.getByText('Recorded')).toBeInTheDocument()
    expect(screen.getByText('Not yet graded')).toBeInTheDocument()
  })

  it('reports a typed value up, and commits the line on blur', () => {
    const { onChange, onCommit } = renderTable()

    const field = screen.getByRole('spinbutton', { name: /mark for omar student/i })
    fireEvent.change(field, { target: { value: '71' } })
    expect(onChange).toHaveBeenCalledWith(6, '71')

    fireEvent.blur(field)
    expect(onCommit).toHaveBeenCalledWith(6)
  })

  it('locks only the row being saved', () => {
    renderTable({ savingEnrollmentId: 5 })

    expect(screen.getByRole('spinbutton', { name: /mark for sara student/i })).toBeDisabled()
    expect(screen.getByRole('spinbutton', { name: /mark for omar student/i })).toBeEnabled()
  })

  it("surfaces a row's save failure beside its own input", () => {
    renderTable({ errors: { 6: 'That enrollment is not a gradable line on this grade sheet.' } })

    expect(screen.getByText(/not a gradable line/i)).toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: /mark for omar student/i })).toHaveAttribute(
      'aria-invalid',
      'true',
    )
  })
})
