// Design tokens — the machine-readable source of truth for the PRS design system
// (human-readable catalogue: docs/design-system.md). This module is MUI-agnostic:
// it imports nothing and exports plain typed data. `createAppTheme` is the thin
// adapter that turns these into an MUI theme. Three tiers: primitive -> semantic
// -> (component, added when needed). Components read semantic roles, never
// primitives directly.

// ── Tier 1: primitive ramps (raw, meaningless-until-assigned) ──────────────────

export const primitive = {
  // Brand red, sampled from the E-JUST logo. 500 is the canonical brand red.
  brand: {
    50: '#FFF1F3',
    100: '#FFDCE1',
    200: '#FFB3BE',
    300: '#FF8296',
    400: '#F5476A',
    500: '#E4002B',
    600: '#C40024',
    700: '#B00020', // accessible brand-red *text* on light (>= 4.5:1)
    800: '#8A0019',
    900: '#5E0011',
  },
  // Cool-tinted neutrals toward the logo black — carries the bulk of the UI.
  ink: {
    0: '#FFFFFF',
    50: '#F7F8FA',
    100: '#EDEFF2',
    200: '#DDE1E7',
    300: '#C4CAD3',
    400: '#9AA2AE',
    500: '#6B7280',
    600: '#4B5560',
    700: '#333B45',
    800: '#1E242B',
    900: '#111418',
    950: '#0B0E11',
  },
  // Slate-blue secondary — non-destructive interactive accents so interactive
  // colour isn't always red.
  slate: {
    50: '#EEF3FB',
    100: '#D6E2F3',
    200: '#AEC4E6',
    300: '#7E9FD3',
    400: '#547CBD',
    500: '#3B6098',
    600: '#2F4E7D',
    700: '#263F65',
    800: '#1D304D',
    900: '#152238',
  },
  // Semantic hues, tuned to stay distinct from brand red (error is a deeper
  // crimson, never the bright brand red). darkMain = the .main used in dark mode.
  success: { main: '#1E8E52', light: '#E7F5ED', dark: '#14663B', darkMain: '#45C97F' },
  warning: { main: '#B26A00', light: '#FBF0DC', dark: '#8A5200', darkMain: '#E8A33D' },
  error: { main: '#B3261E', light: '#FBEAE8', dark: '#8C1D18', darkMain: '#F2B8B5' },
  info: { main: '#1570C4', light: '#E6F1FB', dark: '#0E4E8C', darkMain: '#7FB2F0' },
  white: '#FFFFFF',
} as const

// ── Tier 2: semantic roles, per colour scheme ──────────────────────────────────
// These are what the theme (and thus components) consume.

export const semantic = {
  light: {
    background: { default: primitive.ink[50], paper: primitive.ink[0] },
    surfaceVariant: primitive.ink[100],
    text: {
      primary: primitive.ink[900],
      secondary: primitive.ink[500],
      disabled: primitive.ink[400],
    },
    divider: primitive.ink[200],
    primary: {
      main: primitive.brand[500],
      dark: primitive.brand[600],
      contrastText: primitive.white,
      text: primitive.brand[700], // accessible red text/links on light
    },
    secondary: { main: primitive.slate[500] },
    success: {
      main: primitive.success.main,
      light: primitive.success.light,
      dark: primitive.success.dark,
    },
    warning: {
      main: primitive.warning.main,
      light: primitive.warning.light,
      dark: primitive.warning.dark,
    },
    error: { main: primitive.error.main, light: primitive.error.light, dark: primitive.error.dark },
    info: { main: primitive.info.main, light: primitive.info.light, dark: primitive.info.dark },
    focusRing: primitive.slate[400],
  },
  dark: {
    background: { default: primitive.ink[950], paper: primitive.ink[900] },
    surfaceVariant: primitive.ink[800],
    text: {
      primary: primitive.ink[50],
      secondary: primitive.ink[300],
      disabled: primitive.ink[600],
    },
    divider: '#2A323B',
    primary: {
      main: primitive.brand[500],
      dark: primitive.brand[600],
      contrastText: primitive.white,
      text: primitive.brand[300], // red text/links on dark
    },
    secondary: { main: primitive.slate[300] },
    success: { main: primitive.success.darkMain },
    warning: { main: primitive.warning.darkMain },
    error: { main: primitive.error.darkMain },
    info: { main: primitive.info.darkMain },
    focusRing: primitive.slate[300],
  },
} as const

// ── Non-colour design values ───────────────────────────────────────────────────

// Type scale in px. Arabic uses the same sizes but the larger `lhAr` leading
// (no capitals + taller glyph stacks need more room). Stored raw; the adapter
// turns size into rem and leading into a unitless ratio.
export const typeScale = {
  h1: { size: 40, lhEn: 48, lhAr: 56, weight: 700 },
  h2: { size: 32, lhEn: 40, lhAr: 46, weight: 700 },
  h3: { size: 28, lhEn: 36, lhAr: 42, weight: 600 },
  h4: { size: 24, lhEn: 32, lhAr: 38, weight: 600 },
  h5: { size: 20, lhEn: 28, lhAr: 34, weight: 600 },
  h6: { size: 16, lhEn: 24, lhAr: 30, weight: 600 },
  subtitle1: { size: 16, lhEn: 24, lhAr: 28, weight: 500 },
  subtitle2: { size: 14, lhEn: 20, lhAr: 24, weight: 500 },
  body1: { size: 16, lhEn: 24, lhAr: 28, weight: 400 },
  body2: { size: 14, lhEn: 20, lhAr: 24, weight: 400 },
  button: { size: 14, lhEn: 24, lhAr: 24, weight: 600 },
  caption: { size: 12, lhEn: 16, lhAr: 20, weight: 400 },
  overline: { size: 12, lhEn: 16, lhAr: 20, weight: 600 },
} as const

// Fallback stacks. IBM Plex Sans / IBM Plex Sans Arabic are self-hosted in a
// later ticket; until then these resolve to the system fallback harmlessly.
export const fontFamily = {
  en: "'IBM Plex Sans', system-ui, 'Segoe UI', Roboto, Arial, sans-serif",
  ar: "'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, sans-serif",
} as const

export const space = 8 // base spacing unit (theme.spacing(1) === 8px)

export const radius = { sm: 4, md: 8, lg: 12, pill: 999 } as const

// Flat / border-led elevation. Shadows are near-invisible in dark, which relies
// on surface/border contrast instead — so one shadow set serves both schemes.
export const shadow = {
  e1: '0 1px 2px rgba(17,20,24,.08), 0 2px 8px rgba(17,20,24,.06)',
  e2: '0 4px 12px rgba(17,20,24,.12), 0 8px 24px rgba(17,20,24,.10)',
  e3: '0 8px 28px rgba(17,20,24,.18)',
} as const

export const motion = {
  duration: { fast: 100, base: 200, slow: 300 },
  easing: 'cubic-bezier(0.2, 0, 0, 1)',
} as const
