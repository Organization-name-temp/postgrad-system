import { useState } from 'react'
import type { FormEvent } from 'react'
import { Alert, Box, Button, Container, TextField, Typography } from '@mui/material'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useServiceHealthCheck } from '../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../shared/api/errors'
import { useAuth } from './useAuth'

export function LoginPage() {
  const navigate = useNavigate()
  const { t } = useTranslation('auth')
  const { login, isLoggingIn, loginError } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()
    try {
      await login(email, password)
      navigate('/', { replace: true })
    } catch {
      // Failure state is surfaced from `loginError` below.
    }
  }

  // The server already localizes its message per Accept-Language; fall back to a
  // translated generic message for non-API failures.
  const errorMessage = loginError ? (apiErrorMessage(loginError) ?? t('login.error')) : undefined

  return (
    <Container maxWidth="sm">
      <Box component="form" onSubmit={handleSubmit} noValidate sx={{ py: 8 }}>
        <Typography variant="h4" component="h1" gutterBottom>
          {t('login.title')}
        </Typography>
        <Typography variant="body2" color="text.secondary" gutterBottom>
          {t('common:appName')}
        </Typography>

        {errorMessage && (
          <Alert severity="error" sx={{ my: 2 }}>
            {errorMessage}
          </Alert>
        )}

        <TextField
          label={t('login.email')}
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          fullWidth
          required
          margin="normal"
          autoComplete="username"
        />
        <TextField
          label={t('login.password')}
          type="password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          fullWidth
          required
          margin="normal"
          autoComplete="current-password"
        />
        <Button type="submit" variant="contained" fullWidth disabled={isLoggingIn} sx={{ mt: 2 }}>
          {isLoggingIn ? t('login.submitting') : t('login.submit')}
        </Button>

        <ApiStatus />
      </Box>
    </Container>
  )
}

/**
 * A subtle liveness readout — the walking-skeleton tracer's home now that the
 * app root is the guarded dashboard. Confirms the API is reachable before a
 * visitor attempts to sign in.
 */
function ApiStatus() {
  const { t } = useTranslation('common')
  const { data, isError } = useServiceHealthCheck()
  const status = isError ? 'unreachable' : (data?.data?.status ?? '…')

  return (
    <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 3 }}>
      {t('apiStatus', { status })}
    </Typography>
  )
}
