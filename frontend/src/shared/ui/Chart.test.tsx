import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import { MemoryRouter } from 'react-router-dom'
import { expect, it } from 'vitest'
import '../i18n/config'
import { Chart } from './Chart'

it('exposes exact series values and the same drill-through through a keyboard accessible table', async () => {
  const user = userEvent.setup()
  render(
    <ThemeProvider theme={createTheme()}>
      <MemoryRouter>
        <Chart
          label="Population"
          categoryLabel="Category"
          valueLabel="Students"
          data={[
            { label: 'Assessed', value: 42, href: '/list?status=assessed' },
            { label: 'Unavailable', value: 3, href: '/list?status=unavailable' },
          ]}
        />
      </MemoryRouter>
    </ThemeProvider>,
  )
  expect(screen.getByRole('img', { name: 'Population' })).toBeInTheDocument()
  await user.click(screen.getByRole('button', { name: 'View as table' }))
  const table = screen.getByRole('table', { name: 'Population' })
  expect(within(table).getByRole('link', { name: 'Assessed' })).toHaveAttribute(
    'href',
    '/list?status=assessed',
  )
  expect(within(table).getByText('42')).toBeInTheDocument()
  expect(within(table).getByText('3')).toBeInTheDocument()
  expect(within(table).getByRole('columnheader', { name: 'Students' })).toBeInTheDocument()
})
