import { useState } from 'react'
import {
  Alert,
  Box,
  Button,
  Chip,
  Divider,
  Paper,
  Stack,
  ToggleButton,
  ToggleButtonGroup,
  Typography,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useFreeAddACourseToTheScheduleadddropPeriod } from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import type { EligibleCourse } from '../registration/classification'
import type { CartMode } from '../registration/cartRules'
import { MeetingTimes } from '../MeetingTimes'

interface AddCoursePanelProps {
  /** The courses the server says are addable to this semester right now (eligible ∩ not enrolled). */
  courses: EligibleCourse[]
  /** Re-read the schedule after a successful add, so the new enrollment shows and the list shrinks. */
  onAdded: () => void
}

/**
 * The free-add affordance on My Schedule during the add/drop period (ticket 07, FR-REG-004).
 * It lists the courses the server has already vetted as addable — eligible, not yet on the
 * schedule, never an external elective — each with a For-credit/Audit toggle and an Add
 * button that mints the enrollment directly (no advisor approval; the advisor is notified).
 * The server re-validates every add and stays the authority; a rejected add surfaces its
 * message inline. Rendered as a labelled landmark section so it is reachable and axe-clean.
 */
export function AddCoursePanel({ courses, onAdded }: AddCoursePanelProps) {
  const { t, i18n } = useTranslation('enrollment')
  const add = useFreeAddACourseToTheScheduleadddropPeriod()
  const [modes, setModes] = useState<Record<number, CartMode>>({})

  const onAdd = async (offeringId: number) => {
    await add.mutateAsync({
      data: { offering_id: offeringId, mode: modes[offeringId] ?? 'Regular' },
    })
    onAdded()
  }

  return (
    <Paper
      component="section"
      aria-labelledby="add-course-heading"
      variant="outlined"
      sx={{ borderRadius: 3, p: { xs: 2, sm: 3 }, mt: 2 }}
    >
      <Typography id="add-course-heading" variant="h6" component="h3">
        {t('schedule.add.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
        {t('schedule.add.intro')}
      </Typography>

      {add.error != null && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {apiErrorMessage(add.error) ?? t('schedule.add.error')}
        </Alert>
      )}

      {courses.length === 0 ? (
        <Typography color="text.secondary">{t('schedule.add.empty')}</Typography>
      ) : (
        <Stack divider={<Divider flexItem />} spacing={1.5}>
          {courses.map((course) => {
            const offeringId = course.offering_id as number
            const name = course.course ? pickLocaleName(course.course, i18n.language) : ''
            const mode = modes[offeringId] ?? 'Regular'
            const busy = add.isPending && add.variables?.data?.offering_id === offeringId

            return (
              <Box
                key={offeringId}
                sx={{
                  display: 'flex',
                  flexWrap: 'wrap',
                  gap: 1.5,
                  justifyContent: 'space-between',
                  alignItems: 'center',
                }}
              >
                <Box sx={{ minWidth: 0 }}>
                  <Typography variant="body2">
                    <Typography component="span" variant="body2" sx={{ fontWeight: 600 }}>
                      {course.course?.code}
                    </Typography>{' '}
                    {name}
                  </Typography>
                  {course.classification && (
                    <Chip
                      size="small"
                      variant="outlined"
                      sx={{ mt: 0.5 }}
                      label={t(`classifications.${course.classification}`)}
                    />
                  )}
                  <Box sx={{ mt: 0.5 }}>
                    <MeetingTimes meetings={course.meetings ?? []} />
                  </Box>
                </Box>

                <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                  <ToggleButtonGroup
                    size="small"
                    exclusive
                    value={mode}
                    onChange={(_event, next: CartMode | null) =>
                      next && setModes((prev) => ({ ...prev, [offeringId]: next }))
                    }
                    aria-label={t('schedule.add.mode.label', { course: course.course?.code ?? '' })}
                  >
                    <ToggleButton value="Regular">{t('schedule.add.mode.Regular')}</ToggleButton>
                    <ToggleButton value="Audit">{t('schedule.add.mode.Audit')}</ToggleButton>
                  </ToggleButtonGroup>
                  <Button
                    variant="outlined"
                    size="small"
                    disabled={add.isPending}
                    onClick={() => onAdd(offeringId)}
                    aria-label={t('schedule.add.addCourse', { course: course.course?.code ?? '' })}
                  >
                    {busy ? t('schedule.add.adding') : t('schedule.add.add')}
                  </Button>
                </Stack>
              </Box>
            )
          })}
        </Stack>
      )}
    </Paper>
  )
}
