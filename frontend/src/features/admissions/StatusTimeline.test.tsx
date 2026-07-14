import { describe, expect, it } from 'vitest'
import type { ReactElement } from 'react'
import { render, screen, within } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import '../../shared/i18n/config' // initialise i18next so useTranslation resolves labels
import { StatusTimeline } from './StatusTimeline'
import type { StatusTimeline200DataItem } from '../../shared/api/generated/endpoints'

// The timeline renders StatusChips, which read CSS-variable palette entries for the
// soft variant, so tests render under a cssVariables theme (as the app always does).
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })
const renderTimeline = (ui: ReactElement) =>
  render(<ThemeProvider theme={theme}>{ui}</ThemeProvider>)

const entries: StatusTimeline200DataItem[] = [
  {
    id: 1,
    from_status: 'email_verified',
    to_status: 'under_sa_review',
    actor_user_id: 5,
    note: null,
    occurred_at: '2026-07-10T10:00:00+00:00',
  },
  {
    id: 2,
    from_status: 'under_sa_review',
    to_status: 'returned_for_revision',
    actor_user_id: 2,
    note: 'Please re-upload a clearer transcript.',
    occurred_at: '2026-07-11T09:00:00+00:00',
  },
]

describe('StatusTimeline', () => {
  it('renders each transition as a status chip, with the reviewer note', () => {
    renderTimeline(
      <StatusTimeline
        entries={entries}
        currentStatus="returned_for_revision"
        isPending={false}
        isError={false}
      />,
    )

    // Both statuses render as chips (labelled from the statusToChip-driven i18n).
    expect(screen.getByText('Under review')).toBeInTheDocument()
    expect(screen.getByText('Returned for revision')).toBeInTheDocument()
    // The note is shown against its transition.
    expect(screen.getByText('Please re-upload a clearer transcript.')).toBeInTheDocument()
  })

  it('orders the timeline newest-first', () => {
    renderTimeline(
      <StatusTimeline
        entries={entries}
        currentStatus="returned_for_revision"
        isPending={false}
        isError={false}
      />,
    )

    // Scope to the timeline list (the progress Stepper also renders <li> steps).
    const items = within(screen.getByTestId('timeline-list')).getAllByRole('listitem')
    // The most recent transition (returned_for_revision) comes first.
    expect(within(items[0]).getByText('Returned for revision')).toBeInTheDocument()
    expect(within(items[1]).getByText('Under review')).toBeInTheDocument()
  })

  it('shows the empty state when there are no transitions', () => {
    renderTimeline(
      <StatusTimeline entries={[]} currentStatus="draft" isPending={false} isError={false} />,
    )
    expect(screen.getByText('No status changes yet.')).toBeInTheDocument()
  })

  it('shows an error message when the timeline failed to load', () => {
    renderTimeline(
      <StatusTimeline entries={[]} currentStatus="under_sa_review" isPending={false} isError />,
    )
    expect(screen.getByText('Could not load the status history.')).toBeInTheDocument()
  })

  it('renders the progress stage labels', () => {
    renderTimeline(
      <StatusTimeline
        entries={entries}
        currentStatus="under_sa_review"
        isPending={false}
        isError={false}
      />,
    )
    // The coarse progress indicator names each stage.
    expect(screen.getByText('Student Affairs')).toBeInTheDocument()
    expect(screen.getByText('Dean review')).toBeInTheDocument()
    expect(screen.getByText('Decision')).toBeInTheDocument()
  })
})
