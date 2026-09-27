import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, useNavigate } from 'react-router-dom'
import { expect, it } from 'vitest'
import { useCollectionQuery } from './useCollectionQuery'

function Investigation() {
  const { params, setCriteria, setPage } = useCollectionQuery()
  const navigate = useNavigate()
  return (
    <>
      <output aria-label="criteria">{JSON.stringify(params)}</output>
      <button onClick={() => setCriteria({ q: 'أحمد', status: 'pending' })}>Filter</button>
      <button onClick={() => setPage(3)}>Page</button>
      <button onClick={() => navigate(-1)}>Back</button>
    </>
  )
}

it('restores bookmarked criteria and browser history while resetting pages on filter changes', async () => {
  const user = userEvent.setup()
  render(
    <MemoryRouter initialEntries={['/?program_id=7&page=2&sort=-name']}>
      <Investigation />
    </MemoryRouter>,
  )
  const criteria = () => JSON.parse(screen.getByLabelText('criteria').textContent ?? '{}')
  expect(criteria()).toEqual({ program_id: '7', page: 2, sort: '-name', per_page: 25 })
  await user.click(screen.getByText('Filter'))
  expect(criteria()).toEqual({
    program_id: '7',
    page: 1,
    sort: '-name',
    per_page: 25,
    q: 'أحمد',
    status: 'pending',
  })
  await user.click(screen.getByText('Page'))
  expect(criteria().page).toBe(3)
  await user.click(screen.getByText('Back'))
  expect(criteria().page).toBe(1)
  await user.click(screen.getByText('Back'))
  expect(criteria()).toEqual({ program_id: '7', page: 2, sort: '-name', per_page: 25 })
})
