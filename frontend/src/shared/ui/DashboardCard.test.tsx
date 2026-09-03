import { describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import '../i18n/config'
import { DashboardCard } from './DashboardCard'

// One test inherited by every Card (spec §Testing, seam 3): the states are asserted here
// once so no feature has to re-test them. Rendered under a cssVariables theme and a router,
// as the app always is.
const theme = createTheme({
  cssVariables: true,
  colorSchemes: { light: true, dark: true },
  shape: { borderRadius: 8 },
})
const renderCard = (ui: ReactElement) =>
  render(
    <ThemeProvider theme={theme}>
      <MemoryRouter>{ui}</MemoryRouter>
    </ThemeProvider>,
  )

describe('DashboardCard', () => {
  it('renders its title as a labelled region so a screen reader can jump between cards', () => {
    renderCard(<DashboardCard title="Screening queue" />)

    const heading = screen.getByRole('heading', { level: 2, name: 'Screening queue' })
    expect(heading).toHaveClass('MuiTypography-h5')
    expect(getComputedStyle(screen.getByRole('region', { name: 'Screening queue' })).borderRadius).toBe(
      'calc(1.5 * var(--mui-shape-borderRadius))',
    )
    expect(Number(theme.shape.borderRadius) * 1.5).toBe(12)
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
    const { container } = renderCard(
      <DashboardCard
        title="Advisee requests"
        count={<span>0</span>}
        rows={<span>Nadia Bondok</span>}
        isEmpty
        emptyText="You're all caught up."
      />,
    )

    expect(screen.getByText("You're all caught up.")).toBeInTheDocument()
    expect(container.querySelector('svg[aria-hidden="true"]')).toBeInTheDocument()
    expect(screen.queryByText('0')).not.toBeInTheDocument()
    expect(screen.queryByText('Nadia Bondok')).not.toBeInTheDocument()
  })

  it('renders its own error state and retries the failed query', async () => {
    const user = userEvent.setup()
    const onRetry = vi.fn()
    renderCard(
      <DashboardCard
        title="Advisee requests"
        count={<span>6 pending</span>}
        isError
        errorText="Could not load this summary."
        onRetry={onRetry}
      />,
    )

    expect(screen.getByRole('alert')).toHaveTextContent('Could not load this summary.')
    expect(screen.queryByText('6 pending')).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Retry' }))
    expect(onRetry).toHaveBeenCalledOnce()
  })

  it('marks itself busy and holds back its content while pending', () => {
    renderCard(<DashboardCard title="Advisee requests" count={<span>6 pending</span>} isPending />)

    // A skeleton, not a spinner (design-system.md §9); `aria-busy` on the region is the part
    // assistive technology reads, so that is what is asserted.
    expect(screen.getByRole('region', { name: 'Advisee requests' })).toHaveAttribute(
      'aria-busy',
      'true',
    )
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
