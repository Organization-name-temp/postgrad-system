import { Link as RouterLink } from 'react-router-dom'
import {
  Link,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
} from '@mui/material'

export interface ChartDatum {
  label: string
  value: number
  href?: string
}

export interface ChartDataProps {
  label: string
  categoryLabel: string
  valueLabel: string
  data: readonly ChartDatum[]
}

export function ChartDataTable({ label, categoryLabel, valueLabel, data }: ChartDataProps) {
  return (
    <TableContainer>
      <Table size="small" aria-label={label}>
        <TableHead>
          <TableRow>
            <TableCell scope="col">{categoryLabel}</TableCell>
            <TableCell scope="col">{valueLabel}</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {data.map((datum, index) => (
            <TableRow key={index}>
              <TableCell component="th" scope="row">
                {datum.href ? (
                  <Link component={RouterLink} to={datum.href}>
                    {datum.label}
                  </Link>
                ) : (
                  datum.label
                )}
              </TableCell>
              <TableCell>
                <bdi dir="ltr">{datum.value}</bdi>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  )
}
