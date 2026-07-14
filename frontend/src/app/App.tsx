import { lazy, Suspense } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { LoginPage } from '../features/auth/LoginPage'
import { RegisterPage } from '../features/admissions/RegisterPage'
import { RequireAuth } from '../features/auth/RequireAuth'
import { DashboardPage } from '../features/auth/DashboardPage'
import { FacultiesPage } from '../features/catalog/faculties/FacultiesPage'
import { DepartmentsPage } from '../features/catalog/departments/DepartmentsPage'
import { ProgramsPage } from '../features/catalog/programs/ProgramsPage'
import { ProgramEditPage } from '../features/catalog/programs/ProgramEditPage'
import { CoursesPage } from '../features/catalog/courses/CoursesPage'
import { CourseEditPage } from '../features/catalog/courses/CourseEditPage'
import { CatalogTreePage } from '../features/catalog/tree/CatalogTreePage'
import { ApplyPage } from '../features/admissions/wizard/ApplyPage'
import { CyclesPage } from '../features/admin/cycles/CyclesPage'
import { AuthenticatedLayout } from './AuthenticatedLayout'
import { AppThemeProvider } from './AppThemeProvider'

// Dev-only design-system gallery. Gating the lazy import on `import.meta.env.DEV`
// keeps both the route AND its chunk out of production builds.
const StyleGallery = import.meta.env.DEV ? lazy(() => import('./StyleGallery')) : null

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
            <Route path="/register" element={<RegisterPage />} />
            {StyleGallery && (
              <Route
                path="/style"
                element={
                  <Suspense fallback={null}>
                    <StyleGallery />
                  </Suspense>
                }
              />
            )}
            <Route element={<RequireAuth />}>
              <Route element={<AuthenticatedLayout />}>
                <Route path="/" element={<DashboardPage />} />
                <Route path="/apply" element={<ApplyPage />} />
                <Route path="/catalog" element={<CatalogTreePage />} />
                <Route path="/catalog/faculties" element={<FacultiesPage />} />
                <Route path="/catalog/departments" element={<DepartmentsPage />} />
                <Route path="/catalog/programs" element={<ProgramsPage />} />
                <Route path="/catalog/programs/:programId" element={<ProgramEditPage />} />
                <Route path="/catalog/courses" element={<CoursesPage />} />
                <Route path="/catalog/courses/:courseId" element={<CourseEditPage />} />
                <Route path="/admin/cycles" element={<CyclesPage />} />
              </Route>
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </BrowserRouter>
      </AppThemeProvider>
    </QueryClientProvider>
  )
}
