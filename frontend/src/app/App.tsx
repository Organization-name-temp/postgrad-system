import { lazy, Suspense } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { RequestsPage } from '../features/records/RequestsPage'
import { RequestPage } from '../features/records/RequestPage'
import { IssuancePage } from '../features/records/IssuancePage'
import { IssuanceHistoryPage } from '../features/records/IssuanceHistoryPage'
import { PublicVerificationPage } from '../features/records/PublicVerificationPage'
import { TranscriptPage } from '../features/records/TranscriptPage'
import { FeePage } from '../features/records/FeePage'
import { LoginPage } from '../features/auth/LoginPage'
import { RegisterPage } from '../features/admissions/RegisterPage'
import { RequireAuth } from '../features/auth/RequireAuth'
import { GraduationReportPage } from '../features/academic-standing/GraduationReportPage'
import { GraduationStudentListPage } from '../features/academic-standing/GraduationStudentListPage'
import { TerminationReportPage } from '../features/academic-standing/TerminationReportPage'
import { TerminationCaseListPage } from '../features/academic-standing/TerminationCaseListPage'
import { StandingReportPage } from '../features/academic-standing/StandingReportPage'
import { StandingStudentListPage } from '../features/academic-standing/StandingStudentListPage'
import { ACADEMIC_REPORT_ROLES } from '../features/academic-standing/academicReportAccess'
import { FacultiesPage } from '../features/catalog/faculties/FacultiesPage'
import { DepartmentsPage } from '../features/catalog/departments/DepartmentsPage'
import { ProgramsPage } from '../features/catalog/programs/ProgramsPage'
import { ProgramEditPage } from '../features/catalog/programs/ProgramEditPage'
import { CoursesPage } from '../features/catalog/courses/CoursesPage'
import { CourseEditPage } from '../features/catalog/courses/CourseEditPage'
import { CatalogTreePage } from '../features/catalog/tree/CatalogTreePage'
import { ApplyPage } from '../features/admissions/wizard/ApplyPage'
import { ScreeningQueuePage } from '../features/admissions/screening/ScreeningQueuePage'
import { ApplicationPipelineReportPage } from '../features/admissions/reports/ApplicationPipelineReportPage'
import { ApplicationReviewPage } from '../features/admissions/screening/ApplicationReviewPage'
import { DeanQueuePage } from '../features/admissions/dean/DeanQueuePage'
import { DeanReviewPage } from '../features/admissions/dean/DeanReviewPage'
import { DeanDecisionQueuePage } from '../features/admissions/dean/DeanDecisionQueuePage'
import { DeanDecisionPage } from '../features/admissions/dean/DeanDecisionPage'
import { SlotsPage } from '../features/admissions/interviews/SlotsPage'
import { SchedulingPage } from '../features/admissions/interviews/SchedulingPage'
import { InterviewerPage } from '../features/admissions/interviews/InterviewerPage'
import { CyclesPage } from '../features/admin/cycles/CyclesPage'
import { SemestersPage } from '../features/admin/semesters/SemestersPage'
import { OfferingsPage } from '../features/admin/offerings/OfferingsPage'
import { AssignmentsPage } from '../features/admin/assignments/AssignmentsPage'
import { DepartmentHeadsPage } from '../features/admin/assignments/DepartmentHeadsPage'
import { RegistrationPage } from '../features/enrollment/registration/RegistrationPage'
import { SchedulePage } from '../features/enrollment/schedule/SchedulePage'
import { ManualExemptionsPage } from '../features/enrollment/exemptions/ManualExemptionsPage'
import { ExemptionReviewQueuePage } from '../features/enrollment/exemptions/ExemptionReviewQueuePage'
import { TransfersPage } from '../features/enrollment/transfers/TransfersPage'
import { TransferReviewQueuePage } from '../features/enrollment/transfers/TransferReviewQueuePage'
import { WaiversPage } from '../features/enrollment/waivers/WaiversPage'
import { WaiverReviewQueuePage } from '../features/enrollment/waivers/WaiverReviewQueuePage'
import { AdvisingQueuePage } from '../features/enrollment/advising/AdvisingQueuePage'
import { RegistrationReviewPage } from '../features/enrollment/advising/RegistrationReviewPage'
import { StudentSchedulePage } from '../features/enrollment/advising/StudentSchedulePage'
import { ComposeForStudentPage } from '../features/enrollment/on-behalf/ComposeForStudentPage'
import { MyOfferingsPage } from '../features/enrollment/instructor/MyOfferingsPage'
import { OfferingRegisterPage } from '../features/enrollment/instructor/OfferingRegisterPage'
import { GradeSheetsPage } from '../features/grading/instructor/GradeSheetsPage'
import { GradeSheetPage } from '../features/grading/instructor/GradeSheetPage'
import { CorrectionSheetPage } from '../features/grading/instructor/CorrectionSheetPage'
import { OutstandingIncompletesPage } from '../features/grading/instructor/OutstandingIncompletesPage'
import { IncompleteResolutionPage } from '../features/grading/instructor/IncompleteResolutionPage'
import { DepartmentHeadQueuePage } from '../features/grading/department-head/DepartmentHeadQueuePage'
import { DepartmentHeadSheetPage } from '../features/grading/department-head/DepartmentHeadSheetPage'
import { MyGradesPage } from '../features/grading/student/MyGradesPage'
import { StudentGradesPage } from '../features/grading/oversight/StudentGradesPage'
import { CreditHourSummaryPage } from '../features/academic-standing/CreditHourSummaryPage'
import { TerminationReviewQueuePage } from '../features/academic-standing/TerminationReviewQueuePage'
import { CapacityOverrideQueuePage } from '../features/enrollment/capacity/CapacityOverrideQueuePage'
import { LifecycleReadOnly } from '../features/enrollment/LifecycleReadOnly'
import { AuthenticatedLayout } from './AuthenticatedLayout'
import { AppThemeProvider } from './AppThemeProvider'
// The Dashboard lives in the app shell, not in a feature: it composes Cards from many
// features, and a feature may not import another feature (phase 4.5, ticket 01).
import { DashboardPage } from './DashboardPage'
import { ROLE, ADMIN_ROLES } from '../shared/access/roles'
import { PERMISSION } from '../shared/access/permissions'

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
            <Route path="/records/verify/:token" element={<PublicVerificationPage />} />
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
                <Route element={<RequireAuth permission="transcript.view_unofficial" />}>
                  <Route path="/records" element={<TranscriptPage />} />
                  <Route path="/records/requests" element={<RequestsPage />} />
                  <Route path="/records/requests/:requestId" element={<RequestPage />} />
                  <Route path="/records/history" element={<IssuanceHistoryPage />} />
                  <Route path="/records/issuances/:issuanceId" element={<IssuancePage />} />
                </Route>
                <Route element={<RequireAuth permission="transcript.issue_official" />}>
                  <Route path="/records/fee" element={<FeePage />} />
                </Route>
                {/* Persona-guarded: only actual Applicants (R01) reach the wizard; an
                    admin who merely inherits application.submit is sent home (ticket 11). */}
                <Route element={<RequireAuth roles={[ROLE.applicant]} />}>
                  <Route path="/apply" element={<ApplyPage />} />
                </Route>
                {/* Persona-guarded: only actual Students (R02) reach registration; an
                    admin who merely inherits courses.register_self is sent home and
                    the server would 403 the eligible list anyway (ticket 04). */}
                <Route element={<RequireAuth roles={[ROLE.student]} />}>
                  <Route
                    path="/registration"
                    element={
                      <LifecycleReadOnly>
                        <RegistrationPage />
                      </LifecycleReadOnly>
                    }
                  />
                  <Route
                    path="/schedule"
                    element={
                      <LifecycleReadOnly>
                        <SchedulePage />
                      </LifecycleReadOnly>
                    }
                  />
                  <Route
                    path="/exemptions"
                    element={
                      <LifecycleReadOnly>
                        <ManualExemptionsPage />
                      </LifecycleReadOnly>
                    }
                  />
                  <Route
                    path="/transfers"
                    element={
                      <LifecycleReadOnly>
                        <TransfersPage />
                      </LifecycleReadOnly>
                    }
                  />
                  <Route
                    path="/waivers"
                    element={
                      <LifecycleReadOnly>
                        <WaiversPage />
                      </LifecycleReadOnly>
                    }
                  />
                  <Route path="/academic-standing" element={<CreditHourSummaryPage />} />
                </Route>
                {/* My Grades (Phase 4, ticket 04): the Student's own Semester GPAs and CGPA.
                    Persona-guarded to R02 — an admin inheriting grades.view_own has no Student
                    record to read (the server 403s it), so the nav/route gate on the role. */}
                <Route element={<RequireAuth roles={[ROLE.student]} />}>
                  <Route path="/grading/my-grades" element={<MyGradesPage />} />
                </Route>
                <Route element={<RequireAuth permission={PERMISSION.viewStudentGrades} />}>
                  <Route path="/grading/students" element={<StudentGradesPage />} />
                  <Route path="/grading/students/:studentId" element={<StudentGradesPage />} />
                </Route>
                {/* Persona-guarded: Instructors (R04) reach My Teaching + the register;
                    attendance is authorised by assignment server-side (ticket 08).
                    Administrators (R09/R10) reach them for oversight — the register unscopes
                    and audits their writes server-side (ADR-0015, ticket 10). */}
                <Route element={<RequireAuth roles={[ROLE.instructor, ...ADMIN_ROLES]} />}>
                  <Route path="/offerings/mine" element={<MyOfferingsPage />} />
                  <Route
                    path="/offerings/:offeringId/register"
                    element={<OfferingRegisterPage />}
                  />
                </Route>
                {/* Persona-guarded: Instructors (R04) reach their grade sheets (Phase 4,
                    ticket 01), and Student Affairs (R05) the same screens as their central
                    entry path (ticket 09) — the list becomes a picker over every Offering, and
                    the sheet editor is reused rather than duplicated. Which Sheets either may
                    reach, and that only an Instructor may sign, is re-enforced server-side. */}
                <Route element={<RequireAuth roles={[ROLE.instructor, ROLE.studentAffairs]} />}>
                  <Route path="/grading" element={<GradeSheetsPage />} />
                  <Route path="/grading/offerings/:offeringId" element={<GradeSheetPage />} />
                  <Route path="/grading/corrections/:sheetId" element={<CorrectionSheetPage />} />
                </Route>
                {/* The Instructor's outstanding Incompletes and their resolution round
                    (ticket 05): declared "mine" is the Offering assignment, re-enforced
                    server-side, and there is no central list of them — so this stays R04. */}
                <Route element={<RequireAuth roles={[ROLE.instructor]} />}>
                  <Route path="/grading/incompletes" element={<OutstandingIncompletesPage />} />
                  <Route
                    path="/grading/incompletes/:incompleteId"
                    element={<IncompleteResolutionPage />}
                  />
                </Route>
                {/* The Department Head's review of a signed Grade Sheet (Phase 4, ticket 03):
                    persona-guarded to R06 — both Instructor and Department Head hold
                    grades.finalize, so the distinguishing gate is the role, not the permission,
                    matching the myOfferings precedent. The Department scope is re-enforced
                    server-side (ScopesToDepartmentHead). */}
                <Route element={<RequireAuth roles={[ROLE.departmentHead]} />}>
                  <Route path="/grading/review" element={<DepartmentHeadQueuePage />} />
                  <Route path="/grading/review/:sheetId" element={<DepartmentHeadSheetPage />} />
                  <Route
                    path="/exemptions/department-head"
                    element={<ExemptionReviewQueuePage stage="departmentHead" />}
                  />
                  <Route
                    path="/transfers/department-head"
                    element={<TransferReviewQueuePage stage="departmentHead" />}
                  />
                  <Route
                    path="/waivers/department-head"
                    element={<WaiverReviewQueuePage stage="departmentHead" />}
                  />
                  <Route
                    path="/registration/capacity-overrides"
                    element={<CapacityOverrideQueuePage />}
                  />
                  <Route
                    path="/termination/department-head"
                    element={<TerminationReviewQueuePage stage="departmentHead" />}
                  />
                </Route>
                <Route element={<RequireAuth roles={[ROLE.advisor]} />}>
                  <Route
                    path="/exemptions/advisor"
                    element={<ExemptionReviewQueuePage stage="advisor" />}
                  />
                  <Route
                    path="/transfers/advisor"
                    element={<TransferReviewQueuePage stage="advisor" />}
                  />
                  <Route
                    path="/waivers/advisor"
                    element={<WaiverReviewQueuePage stage="advisor" />}
                  />
                  <Route
                    path="/termination/advisor"
                    element={<TerminationReviewQueuePage stage="advisor" />}
                  />
                </Route>
                <Route element={<RequireAuth roles={[ROLE.council]} />}>
                  <Route
                    path="/transfers/council"
                    element={<TransferReviewQueuePage stage="council" />}
                  />
                  <Route
                    path="/waivers/council"
                    element={<WaiverReviewQueuePage stage="council" />}
                  />
                  <Route
                    path="/termination/council"
                    element={<TerminationReviewQueuePage stage="council" />}
                  />
                </Route>
                {/* Advisor / Student Affairs registration review (ticket 06): gated on the
                    registration.approve permission (route + API). Advisors are scoped to
                    their advisees server-side; SA + admins review unscoped. The
                    student-schedule oversight view (ticket 10) shares that gate — declared
                    before the dynamic :requestId route so "schedule" isn't read as an id. */}
                <Route path="/advising" element={<AdvisingQueuePage />} />
                <Route path="/advising/schedule" element={<StudentSchedulePage />} />
                <Route path="/advising/:requestId" element={<RegistrationReviewPage />} />
                {/* Register-for-student (ticket 09): staff compose a cart on a student's
                    behalf. Gated on courses.register_for_student (nav + API); an Advisor's
                    picker is scoped to their advisees, Student Affairs sees all. */}
                <Route path="/registration/on-behalf" element={<ComposeForStudentPage />} />
                <Route element={<RequireAuth permission={PERMISSION.screenApplications} />}>
                  <Route
                    path="/analytics/application-pipeline"
                    element={<ApplicationPipelineReportPage />}
                  />
                </Route>
                <Route
                  element={
                    <RequireAuth
                      permission={PERMISSION.monitorAcademicStanding}
                      roles={ACADEMIC_REPORT_ROLES}
                    />
                  }
                >
                  <Route path="/analytics/termination" element={<TerminationReportPage />} />
                  <Route
                    path="/analytics/termination/cases"
                    element={<TerminationCaseListPage />}
                  />
                  <Route
                    path="/analytics/graduation-readiness"
                    element={<GraduationReportPage />}
                  />
                  <Route
                    path="/analytics/graduation-readiness/students"
                    element={<GraduationStudentListPage />}
                  />
                  <Route path="/analytics/academic-standing" element={<StandingReportPage />} />
                  <Route
                    path="/analytics/academic-standing/students"
                    element={<StandingStudentListPage />}
                  />
                </Route>
                <Route path="/screening" element={<ScreeningQueuePage />} />
                <Route path="/screening/:applicationId" element={<ApplicationReviewPage />} />
                <Route path="/dean" element={<DeanQueuePage />} />
                <Route path="/dean/decisions" element={<DeanDecisionQueuePage />} />
                <Route path="/dean/decisions/:applicationId" element={<DeanDecisionPage />} />
                <Route path="/dean/:applicationId" element={<DeanReviewPage />} />
                <Route path="/interviews/scheduling" element={<SchedulingPage />} />
                <Route path="/interviews/slots" element={<SlotsPage />} />
                <Route path="/interviews/mine" element={<InterviewerPage />} />
                <Route path="/catalog" element={<CatalogTreePage />} />
                <Route path="/catalog/faculties" element={<FacultiesPage />} />
                <Route path="/catalog/departments" element={<DepartmentsPage />} />
                <Route path="/catalog/programs" element={<ProgramsPage />} />
                <Route path="/catalog/programs/:programId" element={<ProgramEditPage />} />
                <Route path="/catalog/courses" element={<CoursesPage />} />
                <Route path="/catalog/courses/:courseId" element={<CourseEditPage />} />
                <Route path="/admin/cycles" element={<CyclesPage />} />
                <Route path="/admin/semesters" element={<SemestersPage />} />
                <Route path="/admin/semesters/:semesterId/offerings" element={<OfferingsPage />} />
                <Route path="/admin/assignments" element={<AssignmentsPage />} />
                <Route path="/admin/department-heads" element={<DepartmentHeadsPage />} />
              </Route>
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </BrowserRouter>
      </AppThemeProvider>
    </QueryClientProvider>
  )
}
