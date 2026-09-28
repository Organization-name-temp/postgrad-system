import { useId, type ReactNode } from 'react'
import { Box, GlobalStyles, Stack, Typography, useTheme } from '@mui/material'
import { useTranslation } from 'react-i18next'

/** Owners supply effective criteria; printing never infers them from lookup text. */
export function ReportDocument({
  title,
  criteria,
  children,
}: {
  title: string
  criteria: readonly { label: string; value: string }[]
  children: ReactNode
}) {
  const headingId = useId()
  const theme = useTheme()
  const { t } = useTranslation('common')
  return (
    <Box
      component="article"
      aria-labelledby={headingId}
      dir={theme.direction}
      sx={{
        '@media print': {
          color: theme.palette.common.black,
          backgroundColor: theme.palette.common.white,
          fontFamily: theme.typography.fontFamily,
          '& h1, & h2, & h3': { breakAfter: 'avoid' },
          '& table': { width: '100%', tableLayout: 'fixed' },
          '& thead': { display: 'table-header-group' },
          '& tbody, & tr': { breakInside: 'avoid' },
          '& th, & td': {
            padding: theme.spacing(0.5),
            fontSize: theme.typography.caption.fontSize,
            overflowWrap: 'anywhere',
            whiteSpace: 'normal',
          },
          '& .MuiTableContainer-root': { overflow: 'visible' },
          '& *': {
            color: `${theme.palette.common.black} !important`,
            backgroundColor: `${theme.palette.common.white} !important`,
            backgroundImage: 'none !important',
            boxShadow: 'none !important',
          },
          '& svg': { filter: 'grayscale(1)', breakInside: 'avoid' },
          '& svg text': { fill: `${theme.palette.common.black} !important` },
          '& .MuiChartsAxis-line, & .MuiChartsAxis-tick': {
            stroke: `${theme.palette.common.black} !important`,
          },
        },
      }}
    >
      <GlobalStyles
        styles={{
          '@page': { size: 'A4', margin: '15mm' },
          '@media print': {
            html: {
              colorScheme: 'light !important',
              backgroundColor: `${theme.palette.common.white} !important`,
            },
            body: { backgroundColor: `${theme.palette.common.white} !important` },
          },
        }}
      />
      <Stack spacing={2}>
        <Typography id={headingId} component="h1" variant="h4">
          {title}
        </Typography>
        <Box
          component="section"
          aria-label={t('collection.criteria')}
          sx={{ display: 'none', '@media print': { display: 'block', breakInside: 'avoid' } }}
        >
          <Box component="dl" sx={{ m: 0 }}>
            {criteria.map((criterion) => (
              <Box key={criterion.label} sx={{ display: 'flex', flexWrap: 'wrap', columnGap: 2 }}>
                <Typography component="dt" sx={{ fontWeight: 'fontWeightBold' }}>
                  {criterion.label}
                </Typography>
                <Typography component="dd" sx={{ m: 0 }}>
                  <bdi>{criterion.value}</bdi>
                </Typography>
              </Box>
            ))}
          </Box>
        </Box>
        {children}
      </Stack>
    </Box>
  )
}
