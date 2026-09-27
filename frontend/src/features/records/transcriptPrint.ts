import type { Theme } from '@mui/material/styles'

/** Printed certification must remain legible independently of the screen colour scheme. */
export const transcriptPrintSx = (theme: Theme) => ({
  '@media print': {
    color: theme.palette.common.black,
    backgroundColor: theme.palette.common.white,
    '& table': { width: '100%', tableLayout: 'fixed' },
    '& th, & td': {
      padding: theme.spacing(0.5),
      fontSize: theme.typography.caption.fontSize,
      whiteSpace: 'normal',
      overflowWrap: 'anywhere',
    },
    '& tr': { breakInside: 'avoid' },
    '& .MuiTableContainer-root': { overflow: 'visible' },
    '& *': {
      color: `${theme.palette.common.black} !important`,
      backgroundColor: `${theme.palette.common.white} !important`,
      backgroundImage: 'none !important',
      boxShadow: 'none !important',
    },
  },
})
