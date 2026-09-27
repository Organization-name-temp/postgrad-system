import { Autocomplete, TextField } from '@mui/material'
import { ThemeProvider } from '@mui/material/styles'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { it, expect } from 'vitest'
import { createAppTheme } from './createAppTheme'

it.each([
  ['en', 'Clear', 'Open', 'Close'],
  ['ar', 'مسح', 'فتح', 'إغلاق'],
])('localizes built-in selection controls in %s', async (locale, clear, open, close) => {
  const user = userEvent.setup()
  render(
    <ThemeProvider theme={createAppTheme(locale)}>
      <Autocomplete
        options={['Selected option']}
        defaultValue="Selected option"
        renderInput={(params) => <TextField {...params} label="Selection" />}
      />
    </ThemeProvider>,
  )
  await user.click(screen.getByRole('combobox'))
  expect(screen.getByRole('button', { name: clear })).toBeInTheDocument()
  await user.click(screen.getByRole('button', { name: close }))
  await user.click(screen.getByRole('button', { name: open }))
  expect(screen.getByRole('button', { name: close })).toBeInTheDocument()
  expect(screen.getByRole('combobox')).toHaveValue('Selected option')
})
