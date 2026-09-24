import DashboardIcon from '@mui/icons-material/Dashboard'
import AssignmentIcon from '@mui/icons-material/Assignment'
import TravelExploreIcon from '@mui/icons-material/TravelExplore'
import AccountTreeIcon from '@mui/icons-material/AccountTree'
import ApartmentIcon from '@mui/icons-material/Apartment'
import SchoolIcon from '@mui/icons-material/School'
import MenuBookIcon from '@mui/icons-material/MenuBook'
import EventNoteIcon from '@mui/icons-material/EventNote'
import CalendarMonthIcon from '@mui/icons-material/CalendarMonth'
import FactCheckIcon from '@mui/icons-material/FactCheck'
import GavelIcon from '@mui/icons-material/Gavel'
import HowToRegIcon from '@mui/icons-material/HowToReg'
import SupervisorAccountIcon from '@mui/icons-material/SupervisorAccount'
import ManageAccountsIcon from '@mui/icons-material/ManageAccounts'
import EventAvailableIcon from '@mui/icons-material/EventAvailable'
import ScheduleIcon from '@mui/icons-material/Schedule'
import RecordVoiceOverIcon from '@mui/icons-material/RecordVoiceOver'
import AppRegistrationIcon from '@mui/icons-material/AppRegistration'
import CalendarViewWeekIcon from '@mui/icons-material/CalendarViewWeek'
import RuleIcon from '@mui/icons-material/Rule'
import CoPresentIcon from '@mui/icons-material/CoPresent'
import PersonAddAlt1Icon from '@mui/icons-material/PersonAddAlt1'
import PersonSearchIcon from '@mui/icons-material/PersonSearch'
import GradingIcon from '@mui/icons-material/Grading'
import RateReviewIcon from '@mui/icons-material/RateReview'
import GradeIcon from '@mui/icons-material/Grade'
import HourglassBottomIcon from '@mui/icons-material/HourglassBottom'
import WorkspacePremiumIcon from '@mui/icons-material/WorkspacePremium'
import SwapHorizIcon from '@mui/icons-material/SwapHoriz'
import type { NavItem } from './SideNav'
import { PERMISSION } from '../shared/access/permissions'
import { ROLE, ADMIN_ROLES } from '../shared/access/roles'

/**
 * The app's navigation destinations, classified two ways (ADR-0015, ticket 11):
 *
 * - **Persona** items — "My application", "My interviews" — gate on the user's actual
 *   Role, never on an inherited Permission, so an admin (who holds every Permission,
 *   ADR-0008) is not shown another role's home screen. They stay in the header-less
 *   top group alongside the Dashboard.
 * - **Capability** items gate on a Permission and carry an area `section`
 *   (admissions / catalog / calendar / administration) so the shell renders one
 *   `ListSubheader` per non-empty area.
 *
 * Each later phase (and each catalog entity) appends its own entries. The dev-only
 * `/style` gallery link is added by the ticket that builds that route.
 */
export const navItems: NavItem[] = [
  // Personal / persona items — header-less top group.
  { key: 'dashboard', to: '/', icon: <DashboardIcon /> },
  // "My application" is the Applicant's home (R01); an admin has no Application, so it
  // gates on the Role, not on `application.submit` (which admins inherit).
  {
    key: 'apply',
    to: '/apply',
    icon: <AssignmentIcon />,
    roles: [ROLE.applicant],
  },
  // The interviewer's own queue (ticket 09): gated on the Instructor role, not a
  // permission — an interviewer holds no reviewer permission. Admins see it too, to
  // reach the (now unscoped) evaluation write-path for oversight (ADR-0015, ticket 10).
  {
    key: 'myInterviews',
    to: '/interviews/mine',
    icon: <RecordVoiceOverIcon />,
    roles: [ROLE.instructor, ...ADMIN_ROLES],
  },
  // Admissions — the reviewer / Dean / scheduling capability surfaces.
  {
    key: 'screening',
    to: '/screening',
    icon: <FactCheckIcon />,
    permission: PERMISSION.screenApplications,
    section: 'admissions',
  },
  {
    key: 'deanReview',
    to: '/dean',
    icon: <GavelIcon />,
    permission: PERMISSION.screenApplications,
    section: 'admissions',
  },
  // The Dean's post-interview decisions queue (ticket 12): accept/conditional/waitlist/
  // reject. Gated on applicant.decide — the Dean-only permission (R07).
  {
    key: 'deanDecisions',
    to: '/dean/decisions',
    icon: <HowToRegIcon />,
    permission: PERMISSION.decideApplicants,
    section: 'admissions',
  },
  // Interview scheduling surface (ticket 09): Student Affairs / Dean (interview.schedule).
  {
    key: 'interviewScheduling',
    to: '/interviews/scheduling',
    icon: <ScheduleIcon />,
    permission: PERMISSION.scheduleInterview,
    section: 'admissions',
  },
  {
    key: 'interviewSlots',
    to: '/interviews/slots',
    icon: <EventAvailableIcon />,
    permission: PERMISSION.scheduleInterview,
    section: 'admissions',
  },
  // Catalog — the academic-structure browse + admin CRUD (Phase 1).
  {
    key: 'browse',
    to: '/catalog',
    icon: <TravelExploreIcon />,
    permission: PERMISSION.viewCatalog,
    section: 'catalog',
  },
  {
    key: 'faculties',
    to: '/catalog/faculties',
    icon: <AccountTreeIcon />,
    permission: PERMISSION.viewCatalog,
    section: 'catalog',
  },
  {
    key: 'departments',
    to: '/catalog/departments',
    icon: <ApartmentIcon />,
    permission: PERMISSION.viewCatalog,
    section: 'catalog',
  },
  {
    key: 'programs',
    to: '/catalog/programs',
    icon: <SchoolIcon />,
    permission: PERMISSION.viewCatalog,
    section: 'catalog',
  },
  {
    key: 'courses',
    to: '/catalog/courses',
    icon: <MenuBookIcon />,
    permission: PERMISSION.viewCatalog,
    section: 'catalog',
  },
  // Calendar — the admission-cycle windows (Phase 2) and the academic Semesters
  // (Phase 3): both under calendar.manage, in the same section.
  {
    key: 'cycles',
    to: '/admin/cycles',
    icon: <EventNoteIcon />,
    permission: PERMISSION.manageCalendar,
    section: 'calendar',
  },
  {
    key: 'semesters',
    to: '/admin/semesters',
    icon: <CalendarMonthIcon />,
    permission: PERMISSION.manageCalendar,
    section: 'calendar',
  },
  // Enrollment (Phase 3): the Student's own registration surface. A **persona** item
  // gated on the Student role (R02), not on courses.register_self — an admin inherits
  // that permission but has no Student record, so the server would 403 the eligible
  // list (ADR-0015). The advisor/instructor enrollment surfaces join this section in
  // later tickets.
  {
    key: 'registration',
    to: '/registration',
    icon: <AppRegistrationIcon />,
    section: 'enrollment',
    roles: [ROLE.student],
  },
  // My Schedule (ticket 06): the Student's minted Enrollments per semester. A persona
  // item on the Student role (R02), beside registration — an admin inherits the
  // permission but has no Student record, so it gates on the Role (ADR-0015).
  {
    key: 'schedule',
    to: '/schedule',
    icon: <CalendarViewWeekIcon />,
    section: 'enrollment',
    roles: [ROLE.student],
  },
  {
    key: 'manualExemptions',
    to: '/exemptions',
    icon: <WorkspacePremiumIcon />,
    section: 'enrollment',
    roles: [ROLE.student],
  },
  {
    key: 'exemptionAdvisorQueue',
    to: '/exemptions/advisor',
    icon: <WorkspacePremiumIcon />,
    section: 'enrollment',
    roles: [ROLE.advisor],
  },
  {
    key: 'exemptionHeadQueue',
    to: '/exemptions/department-head',
    icon: <WorkspacePremiumIcon />,
    section: 'enrollment',
    roles: [ROLE.departmentHead],
  },
  {
    key: 'transfers',
    to: '/transfers',
    icon: <SwapHorizIcon />,
    section: 'enrollment',
    roles: [ROLE.student],
  },
  {
    key: 'waivers',
    to: '/waivers',
    icon: <WorkspacePremiumIcon />,
    section: 'enrollment',
    roles: [ROLE.student],
  },
  {
    key: 'transferAdvisorQueue',
    to: '/transfers/advisor',
    icon: <SwapHorizIcon />,
    section: 'enrollment',
    roles: [ROLE.advisor],
  },
  {
    key: 'waiverAdvisorQueue',
    to: '/waivers/advisor',
    icon: <WorkspacePremiumIcon />,
    section: 'enrollment',
    roles: [ROLE.advisor],
  },
  {
    key: 'transferHeadQueue',
    to: '/transfers/department-head',
    icon: <SwapHorizIcon />,
    section: 'enrollment',
    roles: [ROLE.departmentHead],
  },
  {
    key: 'waiverHeadQueue',
    to: '/waivers/department-head',
    icon: <WorkspacePremiumIcon />,
    section: 'enrollment',
    roles: [ROLE.departmentHead],
  },
  {
    key: 'transferCouncilQueue',
    to: '/transfers/council',
    icon: <SwapHorizIcon />,
    section: 'enrollment',
    roles: [ROLE.council],
  },
  {
    key: 'waiverCouncilQueue',
    to: '/waivers/council',
    icon: <WorkspacePremiumIcon />,
    section: 'enrollment',
    roles: [ROLE.council],
  },
  // The Advisor's registration-review queue (ticket 06): approve/trim/reject a Pending
  // request line-by-line. A capability item on registration.approve — held by Advisors
  // (their own advisees, live-scoped) and Student Affairs (unscoped); admins inherit it
  // for oversight (ADR-0015).
  {
    key: 'advising',
    to: '/advising',
    icon: <RuleIcon />,
    permission: PERMISSION.approveCourseRegistration,
    section: 'enrollment',
  },
  // Register-for-student (ticket 09): staff compose a cart on a student's behalf. A
  // capability item on courses.register_for_student — held by Advisors (their advisees) and
  // Student Affairs (any student); admins inherit it for oversight (ADR-0015).
  {
    key: 'registerForStudent',
    to: '/registration/on-behalf',
    icon: <PersonAddAlt1Icon />,
    permission: PERMISSION.registerCoursesForStudent,
    section: 'enrollment',
  },
  // Student-schedule oversight (ticket 10): look up a student and view their schedule +
  // attendance read-only. A capability item on registration.approve — Advisors see their
  // advisees (an incidental start on user story 51), Student Affairs any student; admins
  // inherit it for oversight (ADR-0015). The server re-enforces the advisee scope.
  {
    key: 'studentSchedules',
    to: '/advising/schedule',
    icon: <PersonSearchIcon />,
    permission: PERMISSION.approveCourseRegistration,
    section: 'enrollment',
  },
  // The Instructor's teaching + attendance register (ticket 08). A **persona** item on the
  // Instructor role (R04) — attendance has no permission (authorisation is by assignment),
  // so it gates on the role, not a permission. Administrators (R09/R10) reach it too for
  // oversight: the register unscopes for them server-side and audits their writes (ADR-0015,
  // ticket 10) — the myInterviews precedent for a role-gated admin oversight surface.
  {
    key: 'myOfferings',
    to: '/offerings/mine',
    icon: <CoPresentIcon />,
    section: 'enrollment',
    roles: [ROLE.instructor, ...ADMIN_ROLES],
  },
  // Grading (Phase 4, ticket 01): the grade-sheet list, in its own nav section. A **persona**
  // item on the Instructor role (R04) — and, from ticket 09, on Student Affairs (R05) too, whose
  // `grades.enter` without `grades.finalize` makes the very same screen their central-entry
  // picker over every Offering. It stays role-gated rather than permission-gated because an
  // administrator inherits `grades.enter` but is scoped by Offering assignment like an
  // Instructor, so the permission alone would not describe who this screen is for. The server
  // decides which of the two lists comes back. The Department Head's review queue and the
  // Student's My Grades join this section in later tickets.
  {
    key: 'gradeSheets',
    to: '/grading',
    icon: <GradingIcon />,
    section: 'grading',
    roles: [ROLE.instructor, ROLE.studentAffairs],
  },
  // The Instructor's outstanding Incompletes (ticket 05): declared on their own Sheets,
  // resolved through the same signature + Department-Head review as any other grade. A
  // **persona** item beside "Grade sheets" — the Offering assignment is re-enforced
  // server-side exactly as the Sheet routes are.
  {
    key: 'outstandingIncompletes',
    to: '/grading/incompletes',
    icon: <HourglassBottomIcon />,
    section: 'grading',
    roles: [ROLE.instructor],
  },
  // The Department Head's review queue (ticket 03): a **persona** item on the
  // Department-Head role (R06), beside the Instructor's own grade sheets — both share
  // `grades.finalize`, but only a Head reviews a queue, so this gates on the role rather
  // than the permission (the myOfferings precedent).
  {
    key: 'gradeReview',
    to: '/grading/review',
    icon: <RateReviewIcon />,
    section: 'grading',
    roles: [ROLE.departmentHead],
  },
  // My Grades (ticket 04): the Student's own Semester GPAs and CGPA. A **persona** item on
  // the Student role (R02) — the SchedulePage precedent — since an admin inheriting
  // grades.view_own has no Student record for the page to read.
  {
    key: 'myGrades',
    to: '/grading/my-grades',
    icon: <GradeIcon />,
    section: 'grading',
    roles: [ROLE.student],
  },
  // Student-grade oversight (ticket 08): the scoped reviewer read of a Student's academic
  // record. A capability item on grades.view_student — Advisors, Instructors, Student
  // Affairs, Department Heads, Deans, Council, plus admins inheriting everything.
  {
    key: 'studentGrades',
    to: '/grading/students',
    icon: <GradeIcon />,
    section: 'grading',
    permission: PERMISSION.viewStudentGrades,
  },
  // Administration (ticket 08): role-gated to System/Super Admins (R09/R10), grouped
  // under its own subheader — the first role-gated (not permission-gated) nav section.
  {
    key: 'assignments',
    to: '/admin/assignments',
    icon: <SupervisorAccountIcon />,
    section: 'administration',
    adminOnly: true,
  },
  // Department-Head↔Department routing (phase 4, ticket 02), beside the Dean map and
  // under the same role gate — it decides where a signed Grade Sheet goes for review.
  {
    key: 'departmentHeads',
    to: '/admin/department-heads',
    icon: <ManageAccountsIcon />,
    section: 'administration',
    adminOnly: true,
  },
]
