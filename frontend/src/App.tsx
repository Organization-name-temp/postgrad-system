import { Alert, Box, CircularProgress, Container, Typography } from '@mui/material'
import { useServiceHealthCheck } from './shared/api/generated/endpoints'

function App() {
  const { data, isPending, isError } = useServiceHealthCheck()
  const health = data?.data

  return (
    <Container maxWidth="sm">
      <Box sx={{ py: 6 }}>
        <Typography variant="h4" component="h1" gutterBottom>
          Postgraduate Registration System
        </Typography>
        {isPending && <CircularProgress aria-label="loading" />}
        {isError && <Alert severity="error">API unreachable</Alert>}
        {health && (
          <Typography variant="body1">
            API status: {health.status} — App: {health.app}
          </Typography>
        )}
      </Box>
    </Container>
  )
}

export default App
