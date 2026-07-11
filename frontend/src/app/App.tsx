import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { LoginPage } from '../features/auth/LoginPage'
import { RequireAuth } from '../features/auth/RequireAuth'
import { DashboardPage } from '../features/auth/DashboardPage'
import { AuthenticatedLayout } from './AuthenticatedLayout'
import { AppThemeProvider } from './AppThemeProvider'

// retry: false keeps the unauthenticated `/me` probe from retrying a 401, so the
// route guard can redirect promptly instead of waiting out backoff.
const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: false } },
})

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AppThemeProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route element={<RequireAuth />}>
              <Route element={<AuthenticatedLayout />}>
                <Route path="/" element={<DashboardPage />} />
              </Route>
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </BrowserRouter>
      </AppThemeProvider>
    </QueryClientProvider>
  )
}
