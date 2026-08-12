import { Alert, Box, CircularProgress, Link, Paper, Stack, Typography } from '@mui/material'
import ArrowForwardIcon from '@mui/icons-material/ArrowForward'
import { useId } from 'react'
import type { ReactNode } from 'react'
import { Link as RouterLink } from 'react-router-dom'

export interface DashboardCardProps {
  /** The Card's heading. Supplied by the owning feature, already translated. */
  title: string
  /**
   * The headline figure(s) — a count, a GPA pair, a status chip. Rendered above the
   * rows and given the visual weight, because a Card is read at a glance.
   */
  count?: ReactNode
  /** A short list under the count — three rows at most, by convention. */
  rows?: ReactNode
  /**
   * Rendered instead of `count`/`rows` when `isEmpty` — the reason this component
   * exists: every Card inherits an empty state that reads as *"you're all caught
   * up"* rather than as a bare `0`.
   */
  emptyText?: string
  isEmpty?: boolean
  isPending?: boolean
  isError?: boolean
  errorText?: string
  /** Where the Card links into. A Card is a starting point, never a dead end. */
  action?: { to: string; label: string }
}

/**
 * The single presentational Dashboard Card (ADR-0024). It encodes the design *rules* —
 * elevation-by-border, the heading level, the count's weight, the loading/empty/error
 * states, the trailing action link — and knows **no Card names and no domain
 * vocabulary**, the same discipline `<StatusChip>` follows. Each feature supplies its own
 * query hook, row renderer and bilingual copy; the app shell arranges the Cards.
 *
 * States are mutually exclusive and ordered: loading, then error, then empty, then
 * content. An erroring Card renders its own error and nothing else — the grid around it
 * is unaffected, because each Card fetches independently.
 *
 * A11y: the Card is a labelled `section` (a named region in the a11y tree), so a
 * screen-reader user can jump between Cards; the title is an `h2` under the page's `h1`,
 * so the landing page's heading outline stays a single flat level. Lives in `shared/ui`
 * and imports no feature or domain code.
 */
export function DashboardCard({
  title,
  count,
  rows,
  emptyText,
  isEmpty = false,
  isPending = false,
  isError = false,
  errorText,
  action,
}: DashboardCardProps) {
  const titleId = useId()

  return (
    <Paper
      component="section"
      variant="outlined"
      aria-labelledby={titleId}
      sx={{ p: 3, height: '100%', display: 'flex', flexDirection: 'column', gap: 1.5 }}
    >
      <Typography id={titleId} variant="subtitle1" component="h2" sx={{ fontWeight: 600 }}>
        {title}
      </Typography>

      <Box sx={{ flexGrow: 1 }}>
        {isPending && <CircularProgress size={24} aria-label={title} />}

        {!isPending && isError && <Alert severity="error">{errorText}</Alert>}

        {!isPending && !isError && isEmpty && (
          <Typography variant="body2" color="text.secondary">
            {emptyText}
          </Typography>
        )}

        {!isPending && !isError && !isEmpty && (
          <Stack spacing={1.5}>
            {count}
            {rows}
          </Stack>
        )}
      </Box>

      {/* The link is offered even while the Card is loading or erroring: the surface
          behind it is reachable regardless of whether its summary resolved. */}
      {action && (
        <Link
          component={RouterLink}
          to={action.to}
          variant="body2"
          sx={{ display: 'inline-flex', alignItems: 'center', gap: 0.5, alignSelf: 'flex-start' }}
        >
          {action.label}
          {/* Logical rotation so the arrow points into the reading direction in RTL. */}
          <ArrowForwardIcon
            fontSize="inherit"
            sx={(theme) => ({
              transform: theme.direction === 'rtl' ? 'rotate(180deg)' : 'none',
            })}
          />
        </Link>
      )}
    </Paper>
  )
}
