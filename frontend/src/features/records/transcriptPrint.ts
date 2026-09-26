import type { Theme } from '@mui/material/styles'

/** Printed certification must remain legible independently of the screen colour scheme. */
export const transcriptPrintSx = (theme: Theme) => ({
  '@media print': {
    color: theme.palette.common.black,
    backgroundColor: theme.palette.common.white,
    '& *': {
      color: `${theme.palette.common.black} !important`,
      backgroundColor: `${theme.palette.common.white} !important`,
      backgroundImage: 'none !important',
      boxShadow: 'none !important',
    },
  },
})
