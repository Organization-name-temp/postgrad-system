import { Box, Skeleton, Stack } from '@mui/material'

/** Keeps the pending report or collection footprint and announces it once. */
export function ReportLoading({
  label,
  layout = 'chart',
  chartCount = 1,
}: {
  label: string
  layout?: 'chart' | 'table'
  chartCount?: 1 | 2
}) {
  return (
    <Box role="status" aria-label={label} aria-busy="true">
      <Stack spacing={2} aria-hidden="true">
        {Array.from({ length: layout === 'chart' ? chartCount : 1 }, (_, section) => (
          <Stack key={section} spacing={2}>
            {layout === 'chart' && <Skeleton variant="rectangular" height={320} />}
            <Skeleton variant="rectangular" height={40} />
            {Array.from({ length: 5 }, (_, index) => (
              <Skeleton key={index} variant="rectangular" height={40} />
            ))}
          </Stack>
        ))}
      </Stack>
    </Box>
  )
}
