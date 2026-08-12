import { describe, expect, it } from 'vitest'
import type { ReactElement } from 'react'
import { render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import { DashboardCard } from './DashboardCard'

// One test inherited by every Card (spec §Testing, seam 3): the states are asserted here
// once so no feature has to re-test them. Rendered under a cssVariables theme and a router,
// as the app always is.
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })
const renderCard = (ui: ReactElement) =>
  render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>{ui}</MemoryRouter>
    </ThemeProvider>,
  )

describe('DashboardCard', () => {
  it('renders its title as a labelled region so a screen reader can jump between cards', () => {
    renderCard(<DashboardCard title="Screening queue" />)

    expect(screen.getByRole('heading', { level: 2, name: 'Screening queue' })).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Screening queue' })).toBeInTheDocument()
  })

  it('renders the count and the rows when populated', () => {
    renderCard(
      <DashboardCard
        title="Advisee requests"
        count={<span>6 pending</span>}
        rows={<span>Nadia Bondok</span>}
      />,
    )

    expect(screen.getByText('6 pending')).toBeInTheDocument()
    expect(screen.getByText('Nadia Bondok')).toBeInTheDocument()
  })

  it('renders the supplied empty state instead of the count — never a bare zero', () => {
    renderCard(
      <DashboardCard
        title="Advisee requests"
        count={<span>0</span>}
        rows={<span>Nadia Bondok</span>}
        isEmpty
        emptyText="You're all caught up."
      />,
    )

    expect(screen.getByText("You're all caught up.")).toBeInTheDocument()
    expect(screen.queryByText('0')).not.toBeInTheDocument()
    expect(screen.queryByText('Nadia Bondok')).not.toBeInTheDocument()
  })

  it('renders its own error state and no content when the query failed', () => {
    renderCard(
      <DashboardCard
        title="Advisee requests"
        count={<span>6 pending</span>}
        isError
        errorText="Could not load this summary."
      />,
    )

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load this summary.')
    expect(screen.queryByText('6 pending')).not.toBeInTheDocument()
  })

  it('renders a loading indicator instead of content while pending', () => {
    renderCard(<DashboardCard title="Advisee requests" count={<span>6 pending</span>} isPending />)

    expect(screen.getByRole('progressbar')).toBeInTheDocument()
    expect(screen.queryByText('6 pending')).not.toBeInTheDocument()
  })

  it('renders the action link into the surface behind it', () => {
    renderCard(
      <DashboardCard
        title="My grades"
        action={{ to: '/grading/my-grades', label: 'View my grades' }}
      />,
    )

    expect(screen.getByRole('link', { name: /View my grades/ })).toHaveAttribute(
      'href',
      '/grading/my-grades',
    )
  })

  it('renders no link when no action is supplied', () => {
    renderCard(<DashboardCard title="My grades" />)

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })

  it('keeps the action link reachable while the card is erroring', () => {
    // The summary failing must not cost the user the surface behind it.
    renderCard(
      <DashboardCard
        title="My grades"
        isError
        errorText="Could not load this summary."
        action={{ to: '/grading/my-grades', label: 'View my grades' }}
      />,
    )

    expect(screen.getByRole('link', { name: /View my grades/ })).toBeInTheDocument()
  })
})
