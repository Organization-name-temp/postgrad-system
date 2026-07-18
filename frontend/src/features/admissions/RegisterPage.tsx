import { useState } from 'react'
import type { FormEvent } from 'react'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Container,
  Link,
  MenuItem,
  TextField,
  Typography,
} from '@mui/material'
import { Link as RouterLink, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useListCurrentlyOpenAdmissionCycles } from '../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../shared/api/errors'
import { pickLocaleName } from '../../shared/i18n/localeName'
import { useAuth } from '../auth/useAuth'

/**
 * Public register / start-application screen — the first unauthenticated door
 * into the system (ticket 03). It gates on the open-cycle lookup: when no Cycle
 * is open it shows an "admissions closed" state; when exactly one is open it just
 * takes the applicant's details; when several are open it adds a cycle picker.
 * On success the applicant is signed in (the server established the session) and
 * lands in their application wizard (ticket 04) to fill Step 1.
 */
export function RegisterPage() {
  const navigate = useNavigate()
  const { t, i18n } = useTranslation('admissions')
  const { register, isRegistering, registerError } = useAuth()

  const { data, isPending, isError } = useListCurrentlyOpenAdmissionCycles()
  const openCycles = data?.data ?? []
  const multipleOpen = openCycles.length > 1

  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [cycleId, setCycleId] = useState<number | ''>('')

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()
    try {
      await register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        // Only sent when the applicant had to choose; with one open Cycle the
        // server files the application into it automatically.
        ...(multipleOpen && cycleId !== '' ? { admission_cycle_id: cycleId } : {}),
      })
      navigate('/apply', { replace: true })
    } catch {
      // Failure state is surfaced from `registerError` below.
    }
  }

  const signInLink = (
    <Typography variant="body2" sx={{ mt: 3 }}>
      {t('register.haveAccount')}{' '}
      <Link component={RouterLink} to="/login">
        {t('register.signIn')}
      </Link>
    </Typography>
  )

  if (isPending) {
    return (
      <Container maxWidth="sm">
        <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}>
          <CircularProgress aria-label={t('register.loading')} />
        </Box>
      </Container>
    )
  }

  if (isError) {
    return (
      <Container maxWidth="sm">
        <Box sx={{ py: 8 }}>
          <Alert severity="error">{t('register.loadError')}</Alert>
          {signInLink}
        </Box>
      </Container>
    )
  }

  if (openCycles.length === 0) {
    return (
      <Container maxWidth="sm">
        <Box sx={{ py: 8 }}>
          <Typography variant="h4" component="h1" gutterBottom>
            {t('register.closedTitle')}
          </Typography>
          <Alert severity="info">{t('register.closedBody')}</Alert>
          {signInLink}
        </Box>
      </Container>
    )
  }

  const errorMessage = registerError
    ? (apiErrorMessage(registerError) ?? t('register.error'))
    : undefined

  return (
    <Container maxWidth="sm">
      <Box component="form" onSubmit={handleSubmit} noValidate sx={{ py: 8 }}>
        <Typography variant="h4" component="h1" gutterBottom>
          {t('register.title')}
        </Typography>
        <Typography variant="body2" color="text.secondary" gutterBottom>
          {t('register.intro')}
        </Typography>

        {errorMessage && (
          <Alert severity="error" sx={{ my: 2 }}>
            {errorMessage}
          </Alert>
        )}

        <TextField
          label={t('register.name')}
          value={name}
          onChange={(event) => setName(event.target.value)}
          fullWidth
          required
          margin="normal"
          autoComplete="name"
        />
        <TextField
          label={t('register.email')}
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          fullWidth
          required
          margin="normal"
          autoComplete="username"
        />
        <TextField
          label={t('register.password')}
          type="password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          fullWidth
          required
          margin="normal"
          autoComplete="new-password"
        />
        <TextField
          label={t('register.passwordConfirmation')}
          type="password"
          value={passwordConfirmation}
          onChange={(event) => setPasswordConfirmation(event.target.value)}
          fullWidth
          required
          margin="normal"
          autoComplete="new-password"
        />

        {multipleOpen && (
          <TextField
            select
            label={t('register.cycle')}
            helperText={t('register.cycleHelp')}
            value={cycleId === '' ? '' : String(cycleId)}
            onChange={(event) => setCycleId(Number(event.target.value))}
            fullWidth
            required
            margin="normal"
          >
            {openCycles.map((cycle) => (
              <MenuItem key={cycle.id} value={String(cycle.id)}>
                {pickLocaleName(cycle, i18n.language)}
              </MenuItem>
            ))}
          </TextField>
        )}

        <Button
          type="submit"
          variant="contained"
          fullWidth
          disabled={isRegistering || (multipleOpen && cycleId === '')}
          sx={{ mt: 2 }}
        >
          {isRegistering ? t('register.submitting') : t('register.submit')}
        </Button>

        {signInLink}
      </Box>
    </Container>
  )
}
