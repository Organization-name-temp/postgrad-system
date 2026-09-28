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

function TwoInvestigations() {
  const awaiting = useCollectionQuery(25, 'awaiting')
  const booked = useCollectionQuery(25, 'booked')
  const navigate = useNavigate()
  return (
    <>
      <output aria-label="awaiting criteria">{JSON.stringify(awaiting.params)}</output>
      <output aria-label="booked criteria">{JSON.stringify(booked.params)}</output>
      <button onClick={() => awaiting.setCriteria({ q: 'Ahmed' })}>Filter awaiting</button>
      <button onClick={() => booked.setPage(4)}>Page booked</button>
      <button onClick={() => navigate(-1)}>Back</button>
    </>
  )
}

it('keeps two collections independent in one bookmarked URL and restores both with Back', async () => {
  const user = userEvent.setup()
  render(
    <MemoryRouter initialEntries={['/?awaiting_q=Ali&awaiting_page=2&booked_q=Sara&booked_page=3']}>
      <TwoInvestigations />
    </MemoryRouter>,
  )
  const awaiting = () => JSON.parse(screen.getByLabelText('awaiting criteria').textContent ?? '{}')
  const booked = () => JSON.parse(screen.getByLabelText('booked criteria').textContent ?? '{}')
  expect(awaiting()).toEqual({ q: 'Ali', page: 2, per_page: 25 })
  expect(booked()).toEqual({ q: 'Sara', page: 3, per_page: 25 })
  await user.click(screen.getByText('Filter awaiting'))
  expect(awaiting()).toEqual({ q: 'Ahmed', page: 1, per_page: 25 })
  expect(booked()).toEqual({ q: 'Sara', page: 3, per_page: 25 })
  await user.click(screen.getByText('Page booked'))
  expect(awaiting().q).toBe('Ahmed')
  expect(booked().page).toBe(4)
  await user.click(screen.getByText('Back'))
  expect(booked().page).toBe(3)
  await user.click(screen.getByText('Back'))
  expect(awaiting()).toEqual({ q: 'Ali', page: 2, per_page: 25 })
})

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
