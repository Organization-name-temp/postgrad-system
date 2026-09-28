import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { expect, it, vi } from 'vitest'
import { FilterBar } from './FilterBar'

it('distinguishes an empty reference search from the selected criterion label', () => {
  render(
    <FilterBar
      values={{ program_id: '207' }}
      onChange={vi.fn()}
      fields={[
        {
          key: 'program_id',
          label: 'Program',
          reference: {
            query: '',
            onSearch: vi.fn(),
            selected: { value: '207', label: 'Existing Program' },
            options: [],
            loading: false,
            noOptionsText: 'No matching Programs',
            clearText: 'Clear',
            openText: 'Open',
            closeText: 'Close',
          },
        },
      ]}
    />,
  )
  expect(screen.getByRole('combobox', { name: 'Program' })).toHaveValue('')
})

it('lets the owner search references without local filtering or changing the active criterion', async () => {
  const user = userEvent.setup()
  const search = vi.fn()
  const change = vi.fn()
  render(
    <FilterBar
      values={{ program_id: '207' }}
      onChange={change}
      fields={[
        {
          key: 'program_id',
          label: 'Program',
          reference: {
            query: 'هندسة',
            onSearch: search,
            selected: { value: '207', label: 'Existing Program' },
            options: [{ value: '309', label: 'Computer Engineering' }],
            loading: false,
            noOptionsText: 'No matching Programs',
            clearText: 'Clear',
            openText: 'Open',
            closeText: 'Close',
          },
        },
      ]}
    />,
  )
  const input = screen.getByRole('combobox', { name: 'Program' })
  expect(input).toHaveValue('هندسة')
  await user.click(input)
  await user.click(await screen.findByRole('option', { name: 'Computer Engineering' }))
  expect(change).toHaveBeenCalledWith({ program_id: '309' })
  expect(search).toHaveBeenCalledWith(null)
})

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
