import { useId, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Box, Button, Stack, useTheme } from '@mui/material'
import { BarChart } from '@mui/x-charts/BarChart'
import { LineChart } from '@mui/x-charts/LineChart'
import { PieChart } from '@mui/x-charts/PieChart'
import { useTranslation } from 'react-i18next'
import { ChartDataTable, type ChartDataProps } from './ChartDataTable'

/** One series feeds the graphic, accessible drill-through and printable table. */
export function Chart({
  kind = 'bar',
  ...props
}: ChartDataProps & { kind?: 'bar' | 'line' | 'pie' }) {
  const theme = useTheme()
  const { t } = useTranslation('common')
  const navigate = useNavigate()
  const [expanded, setExpanded] = useState(false)
  const tableId = useId()
  const colors = [...(theme.palette.chart?.categorical ?? [theme.palette.secondary.main])]
  const labels = props.data.map((datum) => datum.label)
  const values = props.data.map((datum) => datum.value)
  const drill = (index: number) => {
    const href = props.data[index]?.href
    if (href) void navigate(href)
  }
  const common = { height: 320, colors, skipAnimation: true }
  return (
    <Stack component="section" aria-label={props.label} spacing={1} sx={{ breakInside: 'avoid' }}>
      <Box role="img" aria-label={props.label}>
        {kind === 'bar' && (
          <BarChart
            {...common}
            xAxis={[{ scaleType: 'band', data: labels }]}
            series={[{ data: values, label: props.valueLabel }]}
            onItemClick={(_, item) => drill(item.dataIndex)}
          />
        )}
        {kind === 'line' && (
          <LineChart
            {...common}
            xAxis={[{ scaleType: 'point', data: labels }]}
            series={[{ data: values, label: props.valueLabel }]}
            onMarkClick={(_, item) => drill(item.dataIndex)}
          />
        )}
        {kind === 'pie' && (
          <PieChart
            {...common}
            series={[
              {
                data: props.data.map((datum, index) => ({
                  id: index,
                  label: datum.label,
                  value: datum.value,
                })),
              },
            ]}
            onItemClick={(_, item) => drill(item.dataIndex)}
          />
        )}
      </Box>
      <Button
        aria-expanded={expanded}
        aria-controls={tableId}
        onClick={() => setExpanded(!expanded)}
        sx={{ alignSelf: 'start', '@media print': { display: 'none' } }}
      >
        {t(expanded ? 'collection.hideTable' : 'collection.viewTable')}
      </Button>
      <Box
        id={tableId}
        sx={{ display: expanded ? 'block' : 'none', '@media print': { display: 'block' } }}
      >
        <ChartDataTable {...props} />
      </Box>
    </Stack>
  )
}
