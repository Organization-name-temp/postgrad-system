import InboxOutlinedIcon from '@mui/icons-material/InboxOutlined'
import ArrowForwardIcon from '@mui/icons-material/ArrowForward'
import { Alert, Box, Button, Link, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { useId } from 'react'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
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
  onRetry?: () => void
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
 * so the Dashboard's heading outline stays a single flat level. Lives in `shared/ui`
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
  onRetry,
  action,
}: DashboardCardProps) {
  const titleId = useId()
  const { t } = useTranslation('common')

  return (
    <Paper
      component="section"
      variant="outlined"
      aria-labelledby={titleId}
      aria-busy={isPending}
      sx={{
        p: 3,
        height: '100%',
        display: 'flex',
        flexDirection: 'column',
        gap: 1.5,
        borderRadius: 1.5,
      }}
    >
      <Typography id={titleId} variant="h5" component="h2">
        {title}
      </Typography>

      <Box sx={{ flexGrow: 1 }}>
        {/* Skeletons matching the final layout — one tall count line over two row lines — not a
            spinner (design-system.md §9: skeletons for tables and cards). `aria-busy` on the
            section is what a screen reader reads; the skeletons themselves are decoration. */}
        {isPending && (
          <Stack spacing={1}>
            <Skeleton
              variant="text"
              width="45%"
              sx={(theme) => ({ fontSize: theme.typography.h4.fontSize })}
            />
            <Skeleton variant="text" width="75%" />
            <Skeleton variant="text" width="55%" />
          </Stack>
        )}

        {!isPending && isError && (
          <Alert
            severity="error"
            action={
              onRetry ? (
                <Button color="inherit" size="small" onClick={onRetry}>
                  {t('actions.retry')}
                </Button>
              ) : undefined
            }
          >
            {errorText}
          </Alert>
        )}

        {!isPending && !isError && isEmpty && (
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center', color: 'text.secondary' }}>
            <InboxOutlinedIcon aria-hidden="true" fontSize="small" />
            <Typography variant="body2" color="inherit">
              {emptyText}
            </Typography>
          </Stack>
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
          sx={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: 0.5,
            alignSelf: 'flex-start',
            minHeight: 44,
          }}
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
