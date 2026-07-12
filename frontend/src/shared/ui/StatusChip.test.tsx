import { describe, expect, it } from 'vitest'
import type { ReactElement } from 'react'
import { render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { StatusChip } from './StatusChip'

// StatusChip reads CSS-variable palette entries for the soft variant, so tests
// render under a cssVariables theme (as the app always does).
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })
const renderChip = (ui: ReactElement) => render(<ThemeProvider theme={theme}>{ui}</ThemeProvider>)

describe('StatusChip', () => {
  it('always renders the label (colour is never the only signal)', () => {
    renderChip(<StatusChip tone="success" variant="filled" label="Accepted" />)
    expect(screen.getByText('Accepted')).toBeInTheDocument()
  })

  it('exposes the requested tone and variant as observable data attributes', () => {
    renderChip(<StatusChip tone="warning" variant="filled-soft" label="Waitlisted" />)
    const root = screen.getByText('Waitlisted').closest('[data-tone]')
    expect(root).toHaveAttribute('data-tone', 'warning')
    expect(root).toHaveAttribute('data-variant', 'filled-soft')
  })

  it('renders a leading icon when one is provided', () => {
    renderChip(
      <StatusChip
        tone="error"
        variant="filled"
        label="Rejected"
        icon={<span data-testid="ic" />}
      />,
    )
    expect(screen.getByTestId('ic')).toBeInTheDocument()
  })

  it('renders without an icon when none is provided', () => {
    renderChip(<StatusChip tone="neutral" variant="outlined" label="Draft" />)
    expect(screen.getByText('Draft')).toBeInTheDocument()
    expect(screen.queryByTestId('ic')).not.toBeInTheDocument()
  })

  it('supports the neutral soft combination without error', () => {
    renderChip(<StatusChip tone="neutral" variant="filled-soft" label="Withdrawn" />)
    expect(screen.getByText('Withdrawn')).toBeInTheDocument()
  })
})
