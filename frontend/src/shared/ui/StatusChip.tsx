import { Chip } from '@mui/material'
import type { ChipProps } from '@mui/material'
import type { SxProps, Theme } from '@mui/material/styles'
import type { ReactElement } from 'react'

// Design-system.md §10 tones. `neutral` maps to MUI's default (grey); the rest
// map to the like-named palette colour.
export type StatusTone = 'success' | 'warning' | 'error' | 'info' | 'neutral' | 'secondary'
export type StatusChipVariant = 'filled' | 'filled-soft' | 'outlined'

export interface StatusChipProps {
  tone: StatusTone
  variant: StatusChipVariant
  /** Always rendered — colour is never the only signal (WCAG 1.4.1). */
  label: string
  /** Optional leading icon where it aids scanning. */
  icon?: ReactElement
}

// The five tones that back onto a MUI PaletteColor (with .light/.dark/channels).
// `neutral` is handled separately (there is no palette.default.*).
const TONE_PALETTE = {
  success: 'success',
  warning: 'warning',
  error: 'error',
  info: 'info',
  secondary: 'secondary',
} as const

const iconInherits = { '& .MuiChip-icon': { color: 'inherit' } }

/**
 * filled-soft = a tinted fill + readable text, built to hold WCAG AA contrast in
 * *both* colour schemes. The fill is an alpha tint of the tone (via the channel
 * var, so it flips per scheme); the text is the dark shade in light mode and the
 * light shade in dark mode (theme.applyStyles). Neutral has no palette colour, so
 * it uses the scheme-aware action/text roles.
 */
function filledSoftSx(tone: StatusTone): SxProps<Theme> {
  if (tone === 'neutral') {
    return (theme) => ({
      backgroundColor: theme.vars.palette.action.hover,
      color: theme.vars.palette.text.secondary,
      ...iconInherits,
    })
  }
  const c = TONE_PALETTE[tone]
  // Array form is required to combine a base with theme.applyStyles (MUI docs).
  return [
    (theme) => ({
      backgroundColor: `rgba(${theme.vars.palette[c].mainChannel} / 0.16)`,
      color: theme.vars.palette[c].dark,
      ...iconInherits,
    }),
    (theme) => theme.applyStyles('dark', { color: theme.vars.palette[c].light }),
  ]
}

/**
 * The single presentational status chip. It encodes the design *rules*
 * (filled / filled-soft / outlined) but knows no domain status names — each
 * feature maps its own statuses onto these props. Lives in `shared/ui`; imports
 * no feature/domain code.
 */
export function StatusChip({ tone, variant, label, icon }: StatusChipProps) {
  const color: ChipProps['color'] = tone === 'neutral' ? 'default' : TONE_PALETTE[tone]
  const muiVariant = variant === 'outlined' ? 'outlined' : 'filled'

  return (
    <Chip
      label={label}
      icon={icon}
      color={color}
      variant={muiVariant}
      size="small"
      // Observable, public hooks for tests / e2e / QA — not MUI's internal classes.
      data-tone={tone}
      data-variant={variant}
      sx={variant === 'filled-soft' ? filledSoftSx(tone) : undefined}
    />
  )
}
