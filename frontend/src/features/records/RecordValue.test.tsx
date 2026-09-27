import { act, render, screen } from '@testing-library/react'
import { afterEach, expect, it } from 'vitest'
import i18n from '../../shared/i18n/config'
import { RecordActor, RecordTime } from './RecordValue'

afterEach(async () => {
  await act(() => i18n.changeLanguage('en'))
})

it('preserves an offset timestamp as an explicit UTC instant and isolates Western numerals in Arabic', async () => {
  await act(() => i18n.changeLanguage('ar'))
  render(
    <>
      <RecordTime value="2026-09-27T03:00:00+03:00" />
      <RecordActor id={42} />
    </>,
  )
  const instant = screen.getByText(/2026/)
  expect(instant).toHaveAttribute('dir', 'ltr')
  expect(instant).toHaveTextContent('00:00:00')
  expect(instant.textContent).not.toMatch(/[٠-٩]/)
  expect(screen.getByText('42')).toHaveAttribute('dir', 'ltr')
  expect(screen.getByText(/الموظف/)).toBeInTheDocument()
})
