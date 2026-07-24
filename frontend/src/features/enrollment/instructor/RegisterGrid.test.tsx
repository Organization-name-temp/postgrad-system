import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { RegisterGrid } from './RegisterGrid'
import type { RegisterRosterRow, RegisterSession } from './RegisterGrid'

// StatusChip's soft variant reads CSS-variable palette entries, so render under a
// cssVariables theme (as the app always does — the StatusChip test precedent).
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

const sessions: RegisterSession[] = [
  {
    id: 10,
    date: '2026-02-16',
    weekday: 1,
    starts_at: '09:00',
    ends_at: '11:00',
    cancelled: false,
  },
  { id: 11, date: '2026-02-23', weekday: 1, starts_at: '09:00', ends_at: '11:00', cancelled: true },
]

const roster: RegisterRosterRow[] = [
  {
    enrollment_id: 5,
    course_status: 'Audit',
    student: { name: 'Sara Student', university_id: '2025-MSC-CE-0002' },
    attendance: { recorded: 4, percentage: 50, at_risk: true },
    marks: { '10': true },
  },
  {
    enrollment_id: 6,
    course_status: 'Regular',
    student: { name: 'Omar Student', university_id: '2025-MSC-CE-0003' },
    attendance: { recorded: 0, percentage: 0, at_risk: false },
    marks: {},
  },
]

function renderGrid(overrides: Partial<Parameters<typeof RegisterGrid>[0]> = {}) {
  const onToggleMark = vi.fn()
  const onToggleCancel = vi.fn()
  render(
    <ThemeProvider theme={theme}>
      <RegisterGrid
        sessions={sessions}
        roster={roster}
        pending={{}}
        busy={false}
        onToggleMark={onToggleMark}
        onToggleCancel={onToggleCancel}
        {...overrides}
      />
    </ThemeProvider>,
  )
  return { onToggleMark, onToggleCancel }
}

describe('RegisterGrid', () => {
  it('renders the roster, mode labels, derived percentage, and an at-risk chip', () => {
    renderGrid()

    expect(screen.getByText('Sara Student')).toBeInTheDocument()
    expect(screen.getByText('2025-MSC-CE-0002')).toBeInTheDocument()
    // The registration mode label per student (user story 55).
    expect(screen.getByText('Audit')).toBeInTheDocument()
    expect(screen.getByText('Regular')).toBeInTheDocument()
    // Sara is an Audit enrollment below the minimum → percentage + at-risk chip.
    expect(screen.getByText('50%')).toBeInTheDocument()
    expect(screen.getByText(/at risk/i)).toBeInTheDocument()
    // Omar has no recorded sessions → a dash, no chip.
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('marks the cancelled session as cancelled and disables its toggles', () => {
    renderGrid()

    expect(screen.getByText(/cancelled/i)).toBeInTheDocument()
    // Sara has a present toggle per session; the cancelled session's one is disabled.
    const presentButtons = screen.getAllByRole('button', { name: /mark sara.*present/i })
    expect(presentButtons.some((b) => b.hasAttribute('disabled'))).toBe(true)
    expect(presentButtons.some((b) => !b.hasAttribute('disabled'))).toBe(true)
  })

  it('calls onToggleMark when an unmarked present toggle is clicked', () => {
    const { onToggleMark } = renderGrid()

    // Omar is unmarked, so clicking present selects it (Sara's session-10 toggle is already
    // present, and clicking the active one in an exclusive group deselects — that is ignored).
    const enabled = screen
      .getAllByRole('button', { name: /mark omar.*present/i })
      .find((b) => !b.hasAttribute('disabled'))!
    fireEvent.click(enabled)

    expect(onToggleMark).toHaveBeenCalledWith(10, 6, true)
  })

  it('calls onToggleCancel from a session header', () => {
    const { onToggleCancel } = renderGrid()

    fireEvent.click(screen.getByRole('button', { name: /cancel the session/i }))
    expect(onToggleCancel).toHaveBeenCalledWith(sessions[0])

    fireEvent.click(screen.getByRole('button', { name: /reinstate the session/i }))
    expect(onToggleCancel).toHaveBeenCalledWith(sessions[1])
  })
})
