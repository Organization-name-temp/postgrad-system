import { useState } from 'react'
import {
  Badge,
  Box,
  Button,
  CircularProgress,
  Divider,
  IconButton,
  List,
  ListItem,
  ListItemButton,
  ListItemText,
  Popover,
  Stack,
  Typography,
} from '@mui/material'
import NotificationsIcon from '@mui/icons-material/Notifications'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getListMyNotificationsQueryKey,
  getMyUnreadCountQueryKey,
  useListMyNotifications,
  useMarkAllMyNotificationsRead,
  useMarkOneNotificationRead,
  useMyUnreadCount,
} from '../shared/api/generated/endpoints'
import type { ListMyNotifications200DataItem } from '../shared/api/generated/endpoints'

// The bell polls the unread count on this cadence; opening the panel fetches the
// list on demand. No websockets in this build (spec §Out of Scope).
const POLL_INTERVAL_MS = 30_000

/**
 * Map a server NotificationType value (dotted, e.g. "interview.scheduled") to its
 * dot-free i18n alias under `notifications:types` — i18next treats dots as key
 * separators, so the raw value can't be a leaf key. Colocated with the bell, the
 * same way each feature owns its own status→chip map (design-system.md §10). A
 * type with no alias falls back to a generic line, so the bell renders every
 * type — including ones a later ticket adds before this map learns them.
 */
const TYPE_ALIAS: Record<string, string> = {
  'application.returned': 'applicationReturned',
  'application.approved_for_interview': 'approvedForInterview',
  'interview.scheduled': 'interviewScheduled',
  'interview.reminder': 'interviewReminder',
  'decision.recorded': 'decisionRecorded',
  'application.accepted': 'applicationAccepted',
  'application.overdue': 'applicationOverdue',
  'registration.submitted': 'registrationSubmitted',
  'registration.decided': 'registrationDecided',
  'enrollment.adjusted': 'enrollmentAdjusted',
  'enrollment.withdrawn': 'enrollmentWithdrawn',
  'attendance.audit_at_risk': 'attendanceAuditAtRisk',
  'grade.sheet_submitted': 'gradeSheetSubmitted',
  'grade.sheet_returned': 'gradeSheetReturned',
  'grade.finalized': 'gradeFinalized',
  'incomplete.declared': 'incompleteDeclared',
  'incomplete.reminder': 'incompleteReminder',
  'incomplete.converted': 'incompleteConverted',
  'grades.overdue': 'gradesOverdue',
  'exemption.automatically_applied': 'automaticExemptionApplied',
  'exemption.request_submitted': 'manualExemptionSubmitted',
  'exemption.request_returned': 'manualExemptionReturned',
  'exemption.request_recommended': 'manualExemptionRecommended',
  'exemption.request_decided': 'manualExemptionDecided',
  'transfer.request_submitted': 'transferSubmitted',
  'transfer.request_returned': 'transferReturned',
  'transfer.request_advanced': 'transferAdvanced',
  'transfer.request_decided': 'transferDecided',
  'waiver.request_submitted': 'waiverSubmitted',
  'waiver.request_returned': 'waiverReturned',
  'waiver.request_recommended': 'waiverRecommended',
  'waiver.request_verified': 'waiverVerified',
  'waiver.request_decided': 'waiverDecided',
  'credit_decision.revoked': 'creditDecisionRevoked',
  'academic_standing.changed': 'standingChanged',
}

// Descending unit thresholds (ms) for a coarse localised relative time.
const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
  ['year', 31_536_000_000],
  ['month', 2_592_000_000],
  ['day', 86_400_000],
  ['hour', 3_600_000],
  ['minute', 60_000],
]

/**
 * A localised "2 hours ago" for a timestamp, via Intl.RelativeTimeFormat (EN +
 * AR both supported). Falls to the largest whole unit that fits; anything under
 * a minute reads "just now" (`numeric: 'auto'`).
 */
function relativeTimeFrom(iso: string, locale: string): string {
  const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' })
  const diffMs = new Date(iso).getTime() - Date.now()
  for (const [unit, ms] of RELATIVE_UNITS) {
    if (Math.abs(diffMs) >= ms) {
      return rtf.format(Math.round(diffMs / ms), unit)
    }
  }
  return rtf.format(0, 'second')
}

/**
 * The app-shell notification bell that fills the slot Phase 0.5 reserved. It
 * polls the signed-in user's unread count (shown as a badge) and, when opened,
 * lists their notifications rendered bilingually from the type + payload via the
 * `notifications` i18n namespace (no text is stored server-side, ticket 02).
 * Clicking one — or "mark all as read" — marks it read and refetches, so the
 * badge clears live. Every request is user-scoped by the API.
 */
export function NotificationsBell() {
  const { t, i18n } = useTranslation('notifications')
  const queryClient = useQueryClient()
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null)
  const open = Boolean(anchorEl)

  const unreadQuery = useMyUnreadCount({ query: { refetchInterval: POLL_INTERVAL_MS } })
  const count = unreadQuery.data?.data?.count ?? 0

  // Fetch the list only while the panel is open — the count poll covers the rest.
  const list = useListMyNotifications({ query: { enabled: open } })
  const notifications = list.data?.data ?? []

  const markOne = useMarkOneNotificationRead()
  const markAll = useMarkAllMyNotificationsRead()

  // Relative timestamps ("2 hours ago"), localised (design-system.md §9).
  const relativeTime = (iso: string): string => relativeTimeFrom(iso, i18n.language)

  // Both queries are server-derived, so re-read them after a mutation rather than
  // patching the cache — the count and the read flags then always match the API.
  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: getMyUnreadCountQueryKey() })
    void queryClient.invalidateQueries({ queryKey: getListMyNotificationsQueryKey() })
  }

  const messageFor = (notification: ListMyNotifications200DataItem): string => {
    const alias = (notification.type && TYPE_ALIAS[notification.type]) || 'fallback'
    // `context` selects a variant key (e.g. enrollmentAdjusted_added) when the payload
    // carries an `action`; i18next ignores it when there is no matching suffixed key.
    const data = notification.data as
      { action?: string; outcome?: string; audience?: string } | undefined
    const context = data?.audience ?? data?.action
    const localizedOutcome = data?.outcome ? t(`outcomes.${data.outcome}`) : undefined
    return t(`types.${alias}`, {
      context,
      ...notification.data,
      ...(localizedOutcome ? { outcome: localizedOutcome } : {}),
    })
  }

  const readOne = (notification: ListMyNotifications200DataItem) => {
    if (notification.id === undefined || notification.read_at) {
      return
    }
    markOne.mutate({ notification: notification.id }, { onSuccess: refresh })
  }

  const readAll = () => {
    if (count === 0) {
      return
    }
    markAll.mutate(undefined, { onSuccess: refresh })
  }

  // One interpolated key per case — never concatenate translated fragments, so
  // RTL word order stays correct (design-system.md §11).
  const bellLabel = count > 0 ? t('openWithCount', { count }) : t('open')

  return (
    <>
      <IconButton
        color="inherit"
        aria-label={bellLabel}
        aria-haspopup="true"
        aria-expanded={open ? 'true' : undefined}
        onClick={(event) => setAnchorEl(event.currentTarget)}
      >
        <Badge badgeContent={count} color="primary" max={99}>
          <NotificationsIcon />
        </Badge>
      </IconButton>

      <Popover
        anchorEl={anchorEl}
        open={open}
        onClose={() => setAnchorEl(null)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        transformOrigin={{ vertical: 'top', horizontal: 'right' }}
        slotProps={{ paper: { sx: { width: 360, maxWidth: '100%' } } }}
      >
        <Stack
          direction="row"
          sx={{ alignItems: 'center', justifyContent: 'space-between', px: 2, py: 1, gap: 1 }}
        >
          <Typography variant="subtitle1" component="h2">
            {t('title')}
          </Typography>
          <Button size="small" onClick={readAll} disabled={count === 0 || markAll.isPending}>
            {t('markAllRead')}
          </Button>
        </Stack>
        <Divider />

        {open && list.isPending && (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 3 }}>
            <CircularProgress size={24} aria-label={t('loading')} />
          </Box>
        )}
        {list.isError && (
          <Typography color="error" variant="body2" sx={{ px: 2, py: 2 }}>
            {t('loadError')}
          </Typography>
        )}
        {open && !list.isPending && !list.isError && notifications.length === 0 && (
          <Typography color="text.secondary" variant="body2" sx={{ px: 2, py: 2 }}>
            {t('empty')}
          </Typography>
        )}

        {notifications.length > 0 && (
          <List dense disablePadding>
            {notifications.map((notification) => {
              const unread = !notification.read_at
              return (
                // ListItemButton must sit inside a ListItem (an <li>) so the <ul>
                // has only <li> children (WCAG 1.3.1 list structure). Unread is
                // signalled by weight + a leading dot — never a tinted background,
                // which would drop the secondary text below AA contrast.
                <ListItem key={notification.id} disablePadding>
                  <ListItemButton
                    onClick={() => readOne(notification)}
                    aria-label={unread ? t('markRead') : undefined}
                    sx={{ alignItems: 'flex-start', gap: 1 }}
                  >
                    <Box
                      aria-hidden
                      sx={{
                        mt: 0.75,
                        width: 8,
                        height: 8,
                        flexShrink: 0,
                        borderRadius: '50%',
                        bgcolor: unread ? 'primary.main' : 'transparent',
                      }}
                    />
                    <ListItemText
                      primary={messageFor(notification)}
                      secondary={
                        notification.created_at ? relativeTime(notification.created_at) : ''
                      }
                      slotProps={{ primary: { sx: { fontWeight: unread ? 'bold' : 'normal' } } }}
                    />
                  </ListItemButton>
                </ListItem>
              )
            })}
          </List>
        )}
      </Popover>
    </>
  )
}
