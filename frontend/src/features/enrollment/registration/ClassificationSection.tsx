import {
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import { EligibleCourseRow } from './EligibleCourseRow'
import type { ClassificationGroup } from './classification'

interface ClassificationSectionProps {
  group: ClassificationGroup
}

// The two classifications that carry a caption explaining a rule the student should
// know before choosing (BR-24 external allowance; BR-25 degree-matched research course).
const HINTED = new Set(['ExternalElective', 'ResearchCourse'])

/**
 * One classification group on the registration page — a labelled section with a
 * heading, an optional rule caption, and a table of the eligible courses in that
 * class. The heading is a real `<h2>` tied to the section via `aria-labelledby`, so a
 * screen-reader user can navigate the five groups by landmark (axe-clean).
 */
export function ClassificationSection({ group }: ClassificationSectionProps) {
  const { t } = useTranslation('enrollment')
  const headingId = `enrollment-section-${group.classification}`

  return (
    <Paper
      component="section"
      aria-labelledby={headingId}
      variant="outlined"
      sx={{ borderRadius: 3, p: { xs: 2, sm: 3 }, mb: 3 }}
    >
      <Stack spacing={0.5} sx={{ mb: 2 }}>
        <Typography id={headingId} variant="h6" component="h2">
          {t(`classifications.${group.classification}`)}{' '}
          <Typography component="span" variant="body2" color="text.secondary">
            ({t('countLabel', { count: group.courses.length })})
          </Typography>
        </Typography>
        {HINTED.has(group.classification) && (
          <Typography variant="body2" color="text.secondary">
            {t(`hints.${group.classification}`)}
          </Typography>
        )}
      </Stack>

      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>{t('columns.course')}</TableCell>
              <TableCell>{t('columns.instructor')}</TableCell>
              <TableCell>{t('columns.meetings')}</TableCell>
              <TableCell align="right">{t('columns.seats')}</TableCell>
              <TableCell align="right">{t('columns.creditHours')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {group.courses.map((course) => (
              <EligibleCourseRow key={course.offering_id} course={course} />
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Paper>
  )
}
