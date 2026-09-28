import {
  Alert,
  Box,
  Button,
  Container,
  Divider,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import DeleteOutlinedIcon from '@mui/icons-material/DeleteOutlined'
import WarningAmberIcon from '@mui/icons-material/WarningAmber'
import type { ReactElement, ReactNode } from 'react'
import { StatusChip } from '../shared/ui/StatusChip'
import type { StatusChipVariant, StatusTone } from '../shared/ui/StatusChip'
import { LanguageSwitcher } from './LanguageSwitcher'
import { ThemeModeToggle } from './ThemeModeToggle'
import { Chart } from '../shared/ui/Chart'

// Dev-only living reference for the design system. Content is intentionally in
// English (a developer tool); flipping language proves the theme flips direction
// + font, and flipping the scheme proves the CSS-variable palette. Everything is
// token-driven — no raw colours (it obeys the same no-hex lint rule).

const SWATCHES: { label: string; token: string }[] = [
  { label: 'primary', token: 'primary.main' },
  { label: 'secondary', token: 'secondary.main' },
  { label: 'success', token: 'success.main' },
  { label: 'warning', token: 'warning.main' },
  { label: 'error', token: 'error.main' },
  { label: 'info', token: 'info.main' },
  { label: 'surface', token: 'background.paper' },
  { label: 'background', token: 'background.default' },
]

const TYPE_VARIANTS = [
  'h1',
  'h2',
  'h3',
  'h4',
  'h5',
  'h6',
  'subtitle1',
  'body1',
  'body2',
  'button',
  'caption',
  'overline',
] as const

type Status = { label: string; tone: StatusTone; variant: StatusChipVariant; icon?: ReactElement }

const APPLICATION_STATUS: Status[] = [
  { label: 'Draft', tone: 'neutral', variant: 'outlined' },
  { label: 'Submitted', tone: 'info', variant: 'filled-soft' },
  { label: 'Email Verified', tone: 'info', variant: 'filled-soft' },
  { label: 'Under SA Review', tone: 'secondary', variant: 'filled-soft' },
  { label: 'Accepted', tone: 'success', variant: 'filled' },
  { label: 'Conditionally Accepted', tone: 'warning', variant: 'filled' },
  { label: 'Waitlisted', tone: 'warning', variant: 'outlined' },
  { label: 'Rejected', tone: 'error', variant: 'filled' },
]

const COURSE_STATUS: Status[] = [
  { label: 'Regular', tone: 'neutral', variant: 'outlined' },
  { label: 'Retake', tone: 'warning', variant: 'filled-soft' },
  { label: 'Audit (AU)', tone: 'info', variant: 'outlined' },
  { label: 'Withdrawal (W)', tone: 'neutral', variant: 'outlined' },
  { label: 'Fail-Withdrawal (FW)', tone: 'error', variant: 'filled' },
  { label: 'Incomplete (I)', tone: 'warning', variant: 'filled-soft' },
  { label: 'CGPA-Improvement', tone: 'info', variant: 'filled-soft' },
  { label: 'Transfer', tone: 'secondary', variant: 'outlined' },
  { label: 'Waive', tone: 'secondary', variant: 'outlined' },
  { label: 'Exempted', tone: 'secondary', variant: 'outlined' },
]

const ACADEMIC_STANDING: Status[] = [
  { label: 'Good Standing', tone: 'success', variant: 'filled-soft' },
  { label: 'Academic Warning', tone: 'warning', variant: 'filled-soft' },
  { label: 'Academic Probation', tone: 'warning', variant: 'filled' },
  {
    label: 'Termination Pending',
    tone: 'error',
    variant: 'filled',
    icon: <WarningAmberIcon />,
  },
]

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <Box component="section" sx={{ mt: 5 }}>
      <Typography variant="h4" component="h2" gutterBottom>
        {title}
      </Typography>
      <Divider sx={{ mb: 2 }} />
      {children}
    </Box>
  )
}

function ChipRow({ title, items }: { title: string; items: Status[] }) {
  return (
    <Box sx={{ mb: 2 }}>
      <Typography variant="subtitle2" gutterBottom>
        {title}
      </Typography>
      <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 1 }}>
        {items.map((s) => (
          <StatusChip
            key={s.label}
            tone={s.tone}
            variant={s.variant}
            label={s.label}
            icon={s.icon}
          />
        ))}
      </Stack>
    </Box>
  )
}

export default function StyleGallery() {
  return (
    <Container maxWidth="lg" data-testid="style-gallery" sx={{ py: 4 }}>
      <Stack
        direction="row"
        sx={{ alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 2 }}
      >
        <Typography variant="h2" component="h1">
          PRS Design System
        </Typography>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
          <LanguageSwitcher />
          <ThemeModeToggle />
        </Stack>
      </Stack>

      <Section title="Palette">
        <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 2 }}>
          {SWATCHES.map((sw) => (
            <Box key={sw.label} sx={{ width: 120 }}>
              <Box
                aria-hidden
                sx={{
                  height: 56,
                  borderRadius: 1.5,
                  bgcolor: sw.token,
                  border: '1px solid',
                  borderColor: 'divider',
                }}
              />
              <Typography variant="caption" sx={{ display: 'block', mt: 0.5 }}>
                {sw.label}
              </Typography>
              <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                {sw.token}
              </Typography>
            </Box>
          ))}
        </Stack>
      </Section>

      <Section title="Charts (design-system.md §14)">
        {(['bar', 'line', 'pie'] as const).map((kind) => (
          <Chart
            key={kind}
            kind={kind}
            label={`${kind} chart`}
            categoryLabel="Category"
            valueLabel="Count"
            data={[
              { label: 'A', value: 12 },
              { label: 'B', value: 8 },
              { label: 'C', value: 5 },
            ]}
          />
        ))}
      </Section>

      <Section title="Typography">
        <Stack spacing={1.5}>
          {TYPE_VARIANTS.map((v) => (
            <Typography key={v} variant={v} sx={{ display: 'block' }}>
              {v} — The quick brown fox jumps over the lazy dog
            </Typography>
          ))}
          <Typography variant="body1" lang="ar" dir="rtl" sx={{ display: 'block' }}>
            نموذج نص عربي — نظام تسجيل الدراسات العليا بجامعة إيجاست
          </Typography>
        </Stack>
      </Section>

      <Section title="Status chips (design-system.md §10)">
        <ChipRow title="Application status" items={APPLICATION_STATUS} />
        <ChipRow title="Course status" items={COURSE_STATUS} />
        <ChipRow title="Academic standing" items={ACADEMIC_STANDING} />
      </Section>

      <Section title="Buttons">
        <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 2, alignItems: 'center' }}>
          <Button variant="contained">Primary action</Button>
          <Button variant="outlined" color="secondary">
            Secondary
          </Button>
          <Button variant="text" color="secondary">
            Text
          </Button>
          <Button variant="contained" color="error" startIcon={<DeleteOutlinedIcon />}>
            Delete
          </Button>
          <Button variant="contained" disabled>
            Disabled
          </Button>
        </Stack>
      </Section>

      <Section title="Inputs & surfaces">
        <Stack spacing={2} sx={{ maxWidth: 420 }}>
          <TextField label="University ID" defaultValue="E-JUST-0001" fullWidth />
          <TextField
            label="Email"
            type="email"
            error
            helperText="Enter a valid email address."
            fullWidth
          />
          <Alert severity="success">Grades finalized successfully.</Alert>
          <Alert severity="error">Could not load the resource.</Alert>
          <Paper variant="outlined" sx={{ p: 3, borderRadius: 3 }}>
            <Typography variant="h5" gutterBottom>
              Card
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Flat, border-led elevation (design-system.md §6).
            </Typography>
          </Paper>
        </Stack>
      </Section>
    </Container>
  )
}
