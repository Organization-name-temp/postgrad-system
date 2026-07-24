import { createTheme } from '@mui/material/styles'
import type { PaletteOptions, Shadows, Theme } from '@mui/material/styles'
import { directionFor, isRtlLocale } from '../../shared/i18n/direction'
import { fontFamily, motion, radius, semantic, shadow, space, typeScale } from './tokens'

declare module '@mui/material/styles' {
  // Tell MUI's types that CSS theme variables are enabled, so `theme.vars` and
  // `theme.colorSchemes` are typed (matches `cssVariables` at runtime).
  interface CssThemeVariables {
    enabled: true
  }
  // Custom semantic token not in MUI's palette: the keyboard focus ring. Emitted
  // as `--mui-palette-focusRing` and flipped per colour scheme like any other.
  interface Palette {
    focusRing: string
  }
  interface PaletteOptions {
    focusRing?: string
  }
}

// One mapper for both schemes — the shapes are identical except dark status
// colours give only `.main` (MUI derives light/dark/contrastText from it).
function toPalette(
  mode: 'light' | 'dark',
  s: typeof semantic.light | typeof semantic.dark,
): PaletteOptions {
  return {
    mode,
    // Pick contrast text at the WCAG AA text bar (4.5), not MUI's default 3 — so
    // filled chips/buttons on mid-tone status colours (success/warning) get
    // readable text automatically.
    contrastThreshold: 4.5,
    primary: { main: s.primary.main, dark: s.primary.dark, contrastText: s.primary.contrastText },
    secondary: { main: s.secondary.main },
    error: s.error,
    warning: s.warning,
    info: s.info,
    success: s.success,
    background: s.background,
    text: s.text,
    divider: s.divider,
    focusRing: s.focusRing,
  }
}

// Flat / border-led: a small set of shadows reused up the elevation ramp.
const shadows = Array.from({ length: 25 }, (_, i) =>
  i === 0 ? 'none' : i === 1 ? shadow.e1 : i === 2 ? shadow.e2 : shadow.e3,
) as unknown as Shadows

type Variant = { size: number; lhEn: number; lhAr: number; weight: number }
type VariantExtra = { textTransform?: 'none' | 'uppercase'; letterSpacing?: string }

/**
 * Build the themed MUI theme for one language + direction. Both colour schemes
 * are always baked in — light/dark is a CSS-variable attribute flip at runtime
 * (no recreation). Only direction + font family vary per language, which is why
 * this is recreated on language change (see AppThemeProvider). See
 * docs/design-system.md §3.5.
 */
export function createAppTheme(lang: string): Theme {
  // Direction and Arabic-script both derive from one predicate, so a locale like
  // `ar-EG` can never render LTR with an Arabic font (or vice versa).
  const direction = directionFor(lang)
  const isArabic = isRtlLocale(lang)
  const family = isArabic ? fontFamily.ar : fontFamily.en

  const tv = (v: Variant, extra: VariantExtra = {}) => ({
    fontSize: `${v.size / 16}rem`,
    fontWeight: v.weight,
    // Arabic uses the taller leading; stored as a unitless ratio.
    lineHeight: (isArabic ? v.lhAr : v.lhEn) / v.size,
    ...extra,
  })

  return createTheme({
    direction,
    // 'data-mui-color-scheme' -> selector [data-mui-color-scheme="dark"], which
    // matches the pre-paint script in index.html and MUI's runtime attribute, so
    // the toggle works and there's no flash. (Plain 'data' -> [data-dark]; the
    // default is 'media' = OS-only, no manual toggle — both wrong here.)
    cssVariables: { colorSchemeSelector: 'data-mui-color-scheme' },
    colorSchemes: {
      light: { palette: toPalette('light', semantic.light) },
      dark: { palette: toPalette('dark', semantic.dark) },
    },
    spacing: space,
    shape: { borderRadius: radius.md },
    shadows,
    typography: {
      fontFamily: family,
      h1: tv(typeScale.h1),
      h2: tv(typeScale.h2),
      h3: tv(typeScale.h3),
      h4: tv(typeScale.h4),
      h5: tv(typeScale.h5),
      h6: tv(typeScale.h6),
      subtitle1: tv(typeScale.subtitle1),
      subtitle2: tv(typeScale.subtitle2),
      body1: tv(typeScale.body1),
      body2: tv(typeScale.body2),
      // MUI ALL-CAPS breaks Arabic and looks shouty — off globally.
      button: tv(typeScale.button, { textTransform: 'none' }),
      caption: tv(typeScale.caption),
      // Uppercase is EN-only (design-system.md §4); Arabic has no case, so it
      // just gets the tracking.
      overline: tv(
        typeScale.overline,
        isArabic
          ? { letterSpacing: '0.08em' }
          : { textTransform: 'uppercase', letterSpacing: '0.08em' },
      ),
    },
    transitions: {
      duration: {
        short: motion.duration.fast,
        standard: motion.duration.base,
        complex: motion.duration.slow,
      },
      easing: { easeInOut: motion.easing },
    },
    components: {
      MuiButton: {
        styleOverrides: {
          // Warning's `.main` is a fill colour, not a text colour (design-system.md §12 /
          // the token contrast gate). A text or outlined warning button therefore needs the
          // accessible shade for its label — `.dark` in light, `.light` in dark — the same
          // rule StatusChip applies to soft/outlined tones. (Contained buttons already get
          // readable text from the 4.5 contrastThreshold above.)
          root: ({ ownerState, theme }) =>
            ownerState.color === 'warning' &&
            (ownerState.variant === 'text' || ownerState.variant === 'outlined')
              ? {
                  color: theme.vars.palette.warning.dark,
                  ...theme.applyStyles('dark', { color: theme.vars.palette.warning.light }),
                }
              : {},
        },
      },
      MuiCssBaseline: {
        styleOverrides: (theme) => ({
          // Visible keyboard focus everywhere — never removed without replacement.
          ':focus-visible': {
            // theme.vars flips per colour scheme; theme.palette would freeze the
            // default (light) value.
            outline: `2px solid ${theme.vars.palette.focusRing}`,
            outlineOffset: '2px',
          },
          // Honour reduced-motion (WCAG) — a requirement, not a nicety.
          '@media (prefers-reduced-motion: reduce)': {
            '*, *::before, *::after': {
              animationDuration: '0.01ms !important',
              animationIterationCount: '1 !important',
              transitionDuration: '0.01ms !important',
              scrollBehavior: 'auto !important',
            },
          },
        }),
      },
    },
  })
}
