import { Box, Chip, Paper, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../features/auth/useAuth'
import { dashboardCards } from './dashboardCards'

/**
 * The Dashboard (ADR-0024): the authenticated entry screen, composed of Dashboard Cards
 * drawn from the modules that own their data and selected by the acting User's **own access**
 * — never by a single Role, and with no "view as role" switch. A User holding several Roles
 * sees the union of their Cards on one page.
 *
 * It lives in the app shell rather than in a feature because it composes Cards from many
 * features, and `eslint-plugin-boundaries` forbids a feature importing another feature — the
 * same reason the permission-filtered side nav lives here. The route stays `/`.
 *
 * The filter below is the whole selection rule, read straight off the declarative registry
 * ({@link ./dashboardCards.tsx}): an administrator (R09/R10) sees **only** the administration
 * set, and everyone else sees the Cards whose Permission and Role gates they satisfy. The two
 * branches are independent because membership of the administration set is: a configuration
 * Card belongs to it *and* is seen by the §3.2 holders of its Permission, while a Card
 * exclusive to administrators is one that gates on the admin Roles. This is a UX mirror of the
 * server's authority only — every Card endpoint re-authorizes its own request (ADR-0014).
 */
export function DashboardPage() {
  const { t } = useTranslation('common')
  const { user, hasPermission, hasRole, isAdmin } = useAuth()

  // RequireAuth guarantees an authenticated user before this renders.
  if (!user) {
    return null
  }

  const visible = dashboardCards.filter((card) =>
    isAdmin
      ? card.adminSet === true
      : (!card.permission || hasPermission(card.permission)) &&
        (!card.roles || card.roles.some((role) => hasRole(role))),
  )

  const student = user.student

  return (
    <>
      <Typography variant="h4" component="h1" sx={{ mb: 1 }}>
        {t('dashboard.title')}
      </Typography>

      {/* The slim identity header. The wall of raw permission chips that stood here since
          Phase 0 is gone: name and roles stay so a user can confirm which account they are
          signed in as, and a Student's University ID stays because it is the identifier every
          administrative interaction needs to hand (ADR-0013). */}
      <Paper variant="outlined" sx={{ p: 2, mb: 3 }}>
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          spacing={{ xs: 1, sm: 3 }}
          sx={{ alignItems: { sm: 'center' }, flexWrap: 'wrap' }}
        >
          <Box>
            <Typography variant="h6" component="p">
              {user.name}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {user.email}
            </Typography>
          </Box>

          {student?.university_id && (
            <Box>
              <Typography variant="overline" color="text.secondary">
                {t('dashboard.universityId')}
              </Typography>
              <Typography variant="body1" component="p">
                {student.university_id}
              </Typography>
              {student.enrollment_term && (
                <Typography variant="body2" color="text.secondary">
                  {t('dashboard.enrollmentTerm', {
                    term: t(`terms.${student.enrollment_term}`, {
                      defaultValue: student.enrollment_term,
                    }),
                  })}
                </Typography>
              )}
            </Box>
          )}

          <Box sx={{ flexGrow: 1 }} />

          <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', gap: 1 }}>
            {user.roles?.map((role) => (
              // Kept as `code · name` (the Phase-0 format): the §3.2 role codes are how every
              // document in the project names a role, so they stay legible to a user reading
              // one back to support.
              <Chip
                key={role.code}
                label={`${role.code} · ${t(`roles.${role.code}`, { defaultValue: role.code })}`}
                size="small"
                variant="outlined"
              />
            ))}
          </Stack>
        </Stack>
      </Paper>

      {visible.length === 0 && (
        <Typography color="text.secondary">{t('dashboard.noCards')}</Typography>
      )}

      {/* CSS grid rather than MUI's Grid: the Cards are equal-width and self-contained, so
          one line of template columns is the whole layout, and it mirrors in RTL for free. */}
      <Box
        sx={{
          display: 'grid',
          gap: 3,
          gridTemplateColumns: { xs: '1fr', md: 'repeat(2, minmax(0, 1fr))' },
          alignItems: 'stretch',
        }}
      >
        {visible.map(({ key, component: Card }) => (
          <Card key={key} />
        ))}
      </Box>
    </>
  )
}
