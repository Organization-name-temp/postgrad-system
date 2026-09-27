import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { expect, it, vi } from 'vitest'
import { FilterBar } from './FilterBar'

it('submits labelled text and select criteria and lets the owner clear criteria', async () => {
  const user = userEvent.setup()
  const change = vi.fn()
  render(
    <FilterBar
      fields={[
        { key: 'q', label: 'Search' },
        {
          key: 'status',
          label: 'Status',
          options: [
            { value: '', label: 'All' },
            { value: 'pending', label: 'Pending' },
          ],
        },
      ]}
      values={{ q: 'أحمد', status: 'pending' }}
      onChange={change}
    />,
  )
  expect(screen.getByLabelText('Search')).toHaveValue('أحمد')
  expect(screen.getByRole('combobox', { name: 'Status' })).toHaveTextContent('Pending')
  await user.type(screen.getByLabelText('Search'), 'x')
  expect(change).toHaveBeenCalledWith({ q: 'أحمدx' })
  await user.click(screen.getByRole('combobox', { name: 'Status' }))
  await user.click(screen.getByRole('option', { name: 'All' }))
  expect(change).toHaveBeenCalledWith({ status: '' })
})

it('shows bookmarked comma-separated selections and compound sorts without silently clearing them', () => {
  render(
    <FilterBar
      fields={[
        {
          key: 'status',
          label: 'Status',
          options: [
            { value: '', label: 'All' },
            { value: 'pending', label: 'Pending' },
            { value: 'confirmed', label: 'Confirmed' },
          ],
        },
        {
          key: 'sort',
          label: 'Sort',
          options: [
            { value: '', label: 'Default' },
            { value: '-age', label: 'Longest wait' },
            { value: 'name', label: 'Name' },
          ],
        },
      ]}
      values={{ status: 'pending,confirmed', sort: '-age,name' }}
      onChange={vi.fn()}
    />,
  )
  expect(screen.getByRole('combobox', { name: 'Status' })).toHaveTextContent('Pending, Confirmed')
  expect(screen.getByRole('combobox', { name: 'Sort' })).toHaveTextContent('Longest wait, Name')
})
