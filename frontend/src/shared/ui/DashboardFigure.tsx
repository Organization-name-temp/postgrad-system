import { Stack, Typography } from '@mui/material'
import type { ReactNode } from 'react'

export interface DashboardFigureProps {
  label: ReactNode
  value: ReactNode
}

/** A semantic label/value pair for the headline figures shared by Dashboard Cards. */
export function DashboardFigure({ label, value }: DashboardFigureProps) {
  return (
    <Stack component="dl" spacing={0} sx={{ m: 0 }}>
      <Typography component="dt" variant="overline" color="text.secondary">
        {label}
      </Typography>
      <Typography
        component="dd"
        variant="h4"
        dir="ltr"
        sx={{ m: 0, fontFeatureSettings: '"tnum" 1', fontWeight: 700, unicodeBidi: 'isolate' }}
      >
        {value}
      </Typography>
    </Stack>
  )
}
