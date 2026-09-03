import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { DashboardFigure } from './DashboardFigure'

describe('DashboardFigure', () => {
  it('renders a semantic term and value pair', () => {
    const { container } = render(<DashboardFigure label="Active courses" value={42} />)

    expect(screen.getByText('Active courses').tagName).toBe('DT')
    expect(screen.getByText('42').tagName).toBe('DD')
    expect(container.querySelector('dl')).toBeInTheDocument()
  })
})
