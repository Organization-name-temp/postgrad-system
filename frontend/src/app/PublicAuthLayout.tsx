import { Box, Stack } from '@mui/material'
import { Outlet } from 'react-router-dom'
import { LanguageSwitcher } from './LanguageSwitcher'
import { ThemeModeToggle } from './ThemeModeToggle'

/** App composition gives unauthenticated forms the same presentation controls. */
export function PublicAuthLayout() {
  return (
    <>
      <Stack
        component="header"
        direction="row"
        spacing={1}
        sx={{ px: 2, py: 2, justifyContent: 'flex-end', alignItems: 'center', flexWrap: 'wrap' }}
      >
        <LanguageSwitcher />
        <ThemeModeToggle />
      </Stack>
      <Box component="main">
        <Outlet />
      </Box>
    </>
  )
}
