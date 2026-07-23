import {
  Alert,
  Box,
  Button,
  Chip,
  Divider,
  IconButton,
  Paper,
  Stack,
  ToggleButton,
  ToggleButtonGroup,
  Typography,
} from '@mui/material'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlined'
import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import {
  MAX_CREDIT_HOURS,
  MIN_CREDIT_HOURS,
  type CartIssue,
  type CartLineValue,
  type CartMode,
  type OfferingIndex,
} from './cartRules'

interface CartPanelProps {
  lines: CartLineValue[]
  offerings: OfferingIndex
  issues: CartIssue[]
  total: number
  onSetMode: (offeringId: number, mode: CartMode) => void
  onRemove: (offeringId: number) => void
  onSubmit: () => void
  isSubmitting: boolean
  submitError: unknown
}

/**
 * The registration cart — the selected offerings with a per-line Regular/Audit toggle, a
 * live credit-hour total, and the client-mirrored validation issues (ADR-0014) that block
 * submit until fixed. The server re-validates on submit and stays the authority; these
 * messages are UX only. Labelled as a landmark section so a screen-reader user can jump to
 * their cart.
 */
export function CartPanel({
  lines,
  offerings,
  issues,
  total,
  onSetMode,
  onRemove,
  onSubmit,
  isSubmitting,
  submitError,
}: CartPanelProps) {
  const { t, i18n } = useTranslation('enrollment')

  const outOfRange = issues.some((issue) => issue.code === 'belowMin' || issue.code === 'aboveMax')
  const canSubmit = lines.length > 0 && issues.length === 0 && !isSubmitting

  return (
    <Paper
      component="section"
      aria-labelledby="cart-heading"
      variant="outlined"
      sx={{ borderRadius: 3, p: { xs: 2, sm: 3 }, position: { md: 'sticky' }, top: { md: 16 } }}
    >
      <Typography id="cart-heading" variant="h6" component="h2" sx={{ mb: 2 }}>
        {t('cart.title')}
      </Typography>

      {lines.length === 0 ? (
        <Typography color="text.secondary">{t('cart.empty')}</Typography>
      ) : (
        <Stack divider={<Divider flexItem />} spacing={1.5}>
          {lines.map((line) => {
            const offering = offerings.get(line.offering_id)
            const course = offering?.course
            const name = course ? pickLocaleName(course, i18n.language) : ''

            return (
              <Box key={line.offering_id}>
                <Stack
                  direction="row"
                  spacing={1}
                  sx={{ justifyContent: 'space-between', alignItems: 'flex-start' }}
                >
                  <Box sx={{ minWidth: 0 }}>
                    <Typography variant="body2">
                      <Typography component="span" variant="body2" sx={{ fontWeight: 600 }}>
                        {course?.code}
                      </Typography>{' '}
                      {name}
                    </Typography>
                    {offering?.classification && (
                      <Chip
                        size="small"
                        variant="outlined"
                        sx={{ mt: 0.5 }}
                        label={t(`classifications.${offering.classification}`)}
                      />
                    )}
                  </Box>
                  <Stack spacing={0.5} sx={{ alignItems: 'flex-end' }}>
                    <Typography variant="body2" color="text.secondary">
                      {t('cart.creditHours', { count: offering?.total_ch ?? 0 })}
                    </Typography>
                    <IconButton
                      size="small"
                      onClick={() => onRemove(line.offering_id)}
                      aria-label={t('cart.removeCourse', { course: course?.code ?? '' })}
                    >
                      <DeleteOutlineIcon fontSize="small" />
                    </IconButton>
                  </Stack>
                </Stack>
                <ToggleButtonGroup
                  size="small"
                  exclusive
                  value={line.mode}
                  onChange={(_event, mode: CartMode | null) =>
                    mode && onSetMode(line.offering_id, mode)
                  }
                  aria-label={t('cart.mode.label', { course: course?.code ?? '' })}
                  sx={{ mt: 1 }}
                >
                  <ToggleButton value="Regular">{t('cart.mode.Regular')}</ToggleButton>
                  <ToggleButton value="Audit">{t('cart.mode.Audit')}</ToggleButton>
                </ToggleButtonGroup>
              </Box>
            )
          })}
        </Stack>
      )}

      <Divider sx={{ my: 2 }} />

      <Typography
        variant="subtitle1"
        color={outOfRange ? 'error' : 'text.primary'}
        sx={{ fontWeight: 600 }}
      >
        {t('cart.total', { count: total })}
      </Typography>
      <Typography variant="caption" color="text.secondary">
        {t('cart.range', { min: MIN_CREDIT_HOURS, max: MAX_CREDIT_HOURS })}
      </Typography>

      {issues.length > 0 && (
        <Alert severity="warning" sx={{ mt: 2 }}>
          <Typography variant="body2" sx={{ fontWeight: 600, mb: 0.5 }}>
            {t('cart.fix')}
          </Typography>
          <Box component="ul" sx={{ m: 0, pl: 2.5 }}>
            {issues.map((issue, index) => (
              <li key={`${issue.code}-${index}`}>
                {t(`cart.errors.${issue.code}`, {
                  min: MIN_CREDIT_HOURS,
                  max: MAX_CREDIT_HOURS,
                  total: issue.total ?? total,
                })}
              </li>
            ))}
          </Box>
        </Alert>
      )}

      {submitError != null && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {apiErrorMessage(submitError) ?? t('cart.submitError')}
        </Alert>
      )}

      <Button variant="contained" fullWidth sx={{ mt: 2 }} disabled={!canSubmit} onClick={onSubmit}>
        {isSubmitting ? t('cart.submitting') : t('cart.submit')}
      </Button>
    </Paper>
  )
}
