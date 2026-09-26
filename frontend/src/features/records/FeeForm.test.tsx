import { act, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, expect, it } from 'vitest'
import i18n from '../../shared/i18n/config'
import { FeeForm } from './FeeForm'

afterEach(async () => {
  await act(() => i18n.changeLanguage('en'))
})

it('requires both instruction languages before saving and reports a successful configuration', async () => {
  const user = userEvent.setup()
  render(<FeeForm config={{ available: false, fee: null }} save={async () => {}} />)
  expect(screen.getByRole('button', { name: 'Save fee configuration' })).toBeDisabled()
  await user.type(screen.getByRole('textbox', { name: 'Fee (EGP)' }), '125.50')
  await user.type(
    screen.getByRole('textbox', { name: 'Payment instructions (English)' }),
    'Fictional learning instructions',
  )
  expect(screen.getByRole('button', { name: 'Save fee configuration' })).toBeDisabled()
  await user.type(
    screen.getByRole('textbox', { name: 'Payment instructions (Arabic)' }),
    'تعليمات تعليمية',
  )
  await user.click(screen.getByRole('button', { name: 'Save fee configuration' }))
  expect(await screen.findByText('Fee configuration saved.')).toBeInTheDocument()
})

it('retains entered instructions after a failed save and translates the form in RTL', async () => {
  const user = userEvent.setup()
  await act(() => i18n.changeLanguage('ar'))
  render(
    <FeeForm
      config={{
        available: true,
        fee: '100.00',
        instructions_en: 'Learning',
        instructions_ar: 'تعليمي',
      }}
      save={async () => {
        throw new Error('Failed')
      }}
    />,
  )
  await user.click(screen.getByRole('button', { name: 'حفظ إعداد الرسوم' }))
  expect(await screen.findByRole('alert')).toHaveTextContent('تعذّر حفظ إعداد الرسوم')
  expect(screen.getByRole('textbox', { name: 'تعليمات الدفع (العربية)' })).toHaveValue('تعليمي')
  expect(document.documentElement).toHaveAttribute('dir', 'rtl')
})
