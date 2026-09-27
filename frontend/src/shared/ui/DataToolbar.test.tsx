import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { expect, it, vi } from 'vitest'
import { DataToolbar } from './DataToolbar'
import '../i18n/config'

it('exports through the supplied authenticated downloader and reports a failed download', async () => {
  const user = userEvent.setup()
  const exportRows = vi.fn().mockRejectedValue(new Error('offline'))
  render(
    <DataToolbar total={51} page={2} lastPage={3} onPageChange={vi.fn()} onExport={exportRows} />,
  )
  await user.click(screen.getByRole('button', { name: 'Export all filtered rows' }))
  await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Export failed'))
  expect(exportRows).toHaveBeenCalledTimes(1)
  expect(screen.getByText('51 matching rows')).toBeInTheDocument()
})
