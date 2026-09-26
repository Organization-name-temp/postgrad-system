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
 * Per-variant styling that keeps every chip WCAG AA in both schemes. The tone's
 * `.main` is a fill colour, not a text colour, so soft/outlined labels use the
 * accessible shade: `.dark` in light mode, `.light` in dark mode (via
 * theme.applyStyles).
 *  - `filled`      → MUI's fill + auto contrast text (the theme's 4.5
 *                    contrastThreshold picks readable text). No override.
 *  - `filled-soft` → an alpha tint of the tone (channel var, flips per scheme) +
 *                    accessible text.
 *  - `outlined`    → MUI's `.main` border, label text overridden to accessible.
 * `neutral` has no palette colour, so soft uses the action/text roles and
 * filled/outlined fall back to MUI's already-accessible default.
 */
function chipSx(tone: StatusTone, variant: StatusChipVariant): SxProps<Theme> | undefined {
  if (variant === 'filled') return undefined

  if (tone === 'neutral') {
    if (variant === 'outlined') return undefined
    return (theme) => ({
      backgroundColor: theme.vars.palette.action.hover,
      color: theme.vars.palette.text.primary,
      ...iconInherits,
    })
  }

  const c = TONE_PALETTE[tone]
  const base =
    variant === 'filled-soft'
      ? (theme: Theme) => ({
          backgroundColor: `rgba(${theme.vars.palette[c].mainChannel} / 0.16)`,
          color: theme.vars.palette[c].dark,
          ...iconInherits,
        })
      : (theme: Theme) => ({ color: theme.vars.palette[c].dark, ...iconInherits })
  return [base, (theme) => theme.applyStyles('dark', { color: theme.vars.palette[c].light })]
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
      sx={chipSx(tone, variant)}
    />
  )
}
