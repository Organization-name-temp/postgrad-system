import { render, screen, fireEvent } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { expect, it, vi } from 'vitest'
import '../../shared/i18n/config'
import { RequestActions } from './RequestActions'

it('requires evidence for payment and preserves correction input after a server refusal', async () => {
  const action = vi.fn().mockRejectedValue(new Error('Refused'))
  render(
    <RequestActions
      request={{ status: 'PaymentCorrectionRequired' }}
      staff={false}
      owner
      onAction={action}
    />,
  )
  const user = userEvent.setup()
  await user.click(screen.getByRole('button', { name: 'Submit payment evidence' }))
  expect(action).not.toHaveBeenCalled()
  await user.type(screen.getByLabelText('Payment or refund reference'), 'PAY-ONE')
  fireEvent.change(screen.getByLabelText('Receipt (PDF, JPEG or PNG; up to 10 MB)'), {
    target: { files: [new File(['receipt'], 'receipt.pdf', { type: 'application/pdf' })] },
  })
  await user.click(screen.getByRole('button', { name: 'Submit payment evidence' }))
  expect(action).toHaveBeenCalledWith(
    'payment',
    expect.objectContaining({ reference: 'PAY-ONE', receipt: expect.any(File) }),
  )
  expect(await screen.findByRole('alert')).toHaveTextContent('The action could not be completed')
  expect(screen.getByLabelText('Payment or refund reference')).toHaveValue('PAY-ONE')
})

it('shows cancellation review while paused and requires a staff reason', async () => {
  const action = vi.fn().mockResolvedValue(undefined)
  render(
    <RequestActions
      request={{ status: 'CancellationPending' }}
      staff
      owner={false}
      onAction={action}
    />,
  )
  const user = userEvent.setup()
  expect(screen.queryByRole('button', { name: 'Verify payment' })).not.toBeInTheDocument()
  await user.click(screen.getByRole('button', { name: 'Approve cancellation' }))
  expect(action).not.toHaveBeenCalled()
  await user.type(screen.getByLabelText('Reason'), 'Confirmed cancellation')
  await user.click(screen.getByRole('button', { name: 'Approve cancellation' }))
  expect(action).toHaveBeenCalledWith(
    'cancelApprove',
    expect.objectContaining({ reason: 'Confirmed cancellation' }),
  )
})

it('limits handover to staff and hides cancellation after issuance', () => {
  render(
    <RequestActions
      request={{ status: 'ReadyForCollection', delivery_method: 'collection' }}
      staff={false}
      owner
      onAction={vi.fn()}
    />,
  )
  expect(screen.queryByRole('button')).not.toBeInTheDocument()
})

it('requires exact full-refund inputs and retains uploaded evidence on refusal', async () => {
  const action = vi.fn().mockRejectedValue(new Error('Refused'))
  render(
    <RequestActions
      request={{ status: 'RefundPending', fee: '125.50', refund: { amount: '125.50' } }}
      staff
      owner={false}
      onAction={action}
    />,
  )
  const user = userEvent.setup()
  expect(screen.getByText(/Exactly/)).toHaveTextContent('Exactly 125.50 EGP is required.')
  expect(screen.getByText('125.50')).toHaveAttribute('dir', 'ltr')
  await user.click(screen.getByRole('button', { name: 'Record full refund' }))
  expect(action).not.toHaveBeenCalled()
  await user.type(screen.getByLabelText('Verified payment or full refund (EGP)'), '125.50')
  await user.type(screen.getByLabelText('Payment or refund reference'), 'REF-ONE')
  fireEvent.change(screen.getByLabelText('Receipt (PDF, JPEG or PNG; up to 10 MB)'), {
    target: { files: [new File(['refund'], 'refund.pdf', { type: 'application/pdf' })] },
  })
  await user.click(screen.getByRole('button', { name: 'Record full refund' }))
  expect(action).toHaveBeenCalledWith(
    'refund',
    expect.objectContaining({ amount: '125.50', reference: 'REF-ONE', receipt: expect.any(File) }),
  )
  expect(await screen.findByRole('alert')).toHaveTextContent('The action could not be completed')
  expect(screen.getByLabelText('Verified payment or full refund (EGP)')).toHaveValue('125.50')
})
