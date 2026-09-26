# Software Requirements Specification (SRS)

## Postgraduate Registration System

**Faculties of Engineering (FoE), Basic & Applied Sciences (BAS), Computer Science & IT (CSIT)**

| | |
|---|---|
| **Document Version** | 1.0 |
| **Classification** | Confidential – Production Release |
| **Date** | June 2025 |
| **Organization** | Egypt-Japan University of Science and Technology (E-JUST) |

---

## Table of Contents

- [1. Introduction](#1-introduction)
- [2. System Overview & Scope](#2-system-overview--scope)
- [3. User Roles & Permissions](#3-user-roles--permissions)
- [4. Functional Requirements](#4-functional-requirements)
- [5. Non-Functional Requirements](#5-non-functional-requirements)
- [6. Integration Requirements](#6-integration-requirements)
- [7. Appendices](#7-appendices)

---

## 1. Introduction

### 1.1 Purpose

This Software Requirements Specification (SRS) defines the complete functional and non-functional requirements for the Postgraduate Registration System (PRS) to be deployed in production across the Faculties of Engineering, Basic & Applied Sciences, and Computer Science & IT. The system manages the full lifecycle of postgraduate students from initial application through coursework registration, grading, academic performance monitoring, and transcript generation.

### 1.2 Definitions & Acronyms

| Term | Definition |
|------|-----------|
| PRS | Postgraduate Registration System |
| FoE | Faculty of Engineering |
| BAS | Faculty of Basic & Applied Sciences |
| CSIT | Faculty of Computer Science & Information Technology |
| MSc | Master of Science |
| PhD | Doctor of Philosophy |
| CGPA | Cumulative Grade Point Average |
| GPA | Grade Point Average (per semester) |
| PBL | Project-Based Learning |
| CH | Credit Hour(s) |
| AU | Audit (course status) |
| W | Withdrawal |
| FW | Fail Withdrawal |
| I | Incomplete |
| PRE | Preparatory (course prefix) |
| IELTS | International English Language Testing System |
| TOEFL iBT | Test of English as a Foreign Language – Internet-Based Test |
| RBAC | Role-Based Access Control |
| MFA | Multi-Factor Authentication |
| SSO | Single Sign-On |
| 2FA | Two-Factor Authentication |
| JWT | JSON Web Token |
| E-JUST | Egypt-Japan University of Science and Technology |

### 1.3 References

- University Postgraduate Academic Bylaws
- E-JUST Examination and Grading Policies
- University IT Security Policy
- FERPA / Data Protection Compliance Guidelines

---

## 2. System Overview & Scope

### 2.1 System Context Diagram

The Postgraduate Registration System comprises the following modules:

- Admission Module
- Course Registration
- Grading & GPA
- Transcript & Records
- Exemption Module
- Transfer & Waiver
- Academic Standing
- Notification & Alerts
- Program Management
- Calendar & Schedule
- Reporting & Analytics
- Security & Auth Module

**Users:** Applicant, Student, Faculty & Staff, Admin

### 2.2 Total Required Credit Hours

**Total Required Credit Hours for Graduation: 21 CH**

| Category | Number of Courses | CH per Course | Total CH | Notes |
|----------|:-:|:-:|:-:|-------|
| Preparatory Courses | 6 | 1 | 6 | 4 University Requirements + 2 Departmental Requirements; one departmental entry has the Key Skills role |
| Program Core Courses | 2 | 3 | 6 | Must be from student's program |
| Research Elective Courses | 3 | 3 | 9 | Classified by level and research type |
| **Total** | **11** | | **21** | |

### 2.3 In Scope

- Applicant registration and admission workflow (multi-step)
- Email verification and document upload
- Program and course catalog management (tree view)
- Student course registration with advisor approval
- Exemption processing (automatic and manual)
- Course transfer and waiver management
- Grade entry by instructors/teachers
- Automated GPA/CGPA calculation
- Academic standing monitoring and termination workflows
- Transcript generation and export (official and unofficial)
- Calendar management for interviews and academic deadlines
- Role-based dashboards for all user types
- Notification system (in-app + email)
- Full audit trail and logging
- Multi-language support (Arabic / English)

### 2.4 Out of Scope

- Financial/billing management (integration point only)
- Learning Management System (LMS) functionality
- Thesis/Dissertation management system
- Alumni management
- Physical classroom scheduling

---

## 3. User Roles & Permissions

### 3.1 Role Definitions

| Role ID | Role Name | Description |
|---------|-----------|-------------|
| R01 | Applicant | A prospective student who has not yet been admitted. Can fill out application forms, upload documents, select programs, and track application status. |
| R02 | Student | An admitted and enrolled postgraduate student. Can register courses, view grades, request exemptions/transfers, view transcript. |
| R03 | Academic Advisor | A faculty member assigned to guide students. Can approve/reject course registrations, monitor student academic progress, register courses on behalf of students, and recommend exemptions. |
| R04 | Instructor (Doctor/Teacher) | A faculty member teaching a course. Can enter/sign grades and marks, view enrolled students, manage course-level academic activities. |
| R05 | Student Affairs Staff | Administrative staff responsible for student records. Full access to student data, transcript issuance, manual registration, managing special statuses, and ensuring compliance with bylaws. |
| R06 | Department Head | Head of an academic department. Can approve exemption recommendations, review course equivalencies, and approve departmental requirements. |
| R07 | Dean of Faculty | Dean or authorized representative. Screens and approves admission applications, reviews termination recommendations. |
| R08 | Education & Academic Affairs Council | University-level council. Final approval authority for terminations, policy exceptions, and academic disputes. |
| R09 | System Administrator | IT administrator with full system configuration access. Manages roles, system settings, program/course catalog configuration, academic calendars, and system health monitoring. |
| R10 | Super Administrator | Highest-level system access. Can manage all system administrators, audit logs, and system-wide configurations. |

### 3.2 Permission Matrix

| Function / Module | R01 | R02 | R03 | R04 | R05 | R06 | R07 | R08 |
|-------------------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Submit Application | ✓ | – | – | – | – | – | – | – |
| Upload Documents | ✓ | ✓ | – | – | ✓ | – | – | – |
| View Application Status | ✓ | – | – | – | ✓ | – | ✓ | – |
| Screen/Approve Applications | – | – | – | – | ✓ | – | ✓ | – |
| Schedule Interview | – | – | – | – | ✓ | – | ✓ | – |
| Accept/Reject Applicants | – | – | – | – | – | – | ✓ | – |
| Register Courses (Self) | – | ✓ | – | – | – | – | – | – |
| Register Courses (For Student) | – | – | ✓ | – | ✓ | – | – | – |
| Approve Course Registration | – | – | ✓ | – | ✓ | – | – | – |
| View Course Catalog | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Manage Programs/Courses | – | – | – | – | – | ✓ | ✓ | – |
| Submit Exemption Request | – | ✓ | – | – | – | – | – | – |
| Approve Exemption | – | – | ✓ | – | – | ✓ | – | – |
| Submit Transfer/Waiver | – | ✓ | – | – | – | – | – | – |
| Approve Transfer/Waiver | – | – | ✓ | – | – | ✓ | – | ✓ |
| Enter Grades/Marks | – | – | – | ✓ | ✓ | – | – | – |
| Sign/Finalize Grades | – | – | – | ✓ | – | ✓ | – | – |
| View Own Grades/GPA | – | ✓ | – | – | – | – | – | – |
| View Student Grades/GPA | – | – | ✓ | ✓* | ✓ | ✓ | ✓ | ✓ |
| View Unofficial Transcript | – | ✓ | ✓ | – | ✓ | – | – | – |
| Issue Official Transcript | – | – | – | – | ✓ | – | – | – |
| Export Transcript (PDF) | – | ✓** | – | – | ✓ | – | ✓ | – |
| Monitor Academic Standing | – | ✓*** | ✓ | – | ✓ | ✓ | ✓ | ✓ |
| Recommend Termination | – | – | ✓ | – | – | ✓ | – | – |
| Approve Termination | – | – | – | – | – | – | – | ✓ |
| Manage Academic Calendar | – | – | – | – | ✓ | – | ✓ | – |

> \*Only for enrolled students in their courses
> \*\*Unofficial only
> \*\*\*Own standing only

---

## 4. Functional Requirements

### 4.1 Authentication & Security Module

#### 4.1.1 FR-SEC-001: Login Page

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-SEC-001 |
| **Title** | Secure Login Page |
| **Priority** | Critical |
| **Description** | The system SHALL provide a secure login page as the single entry point for all users. |

**Acceptance Criteria:**

1. The login page SHALL support authentication via university email and password.
2. The system SHALL support Single Sign-On (SSO) integration with the university identity provider (LDAP/Active Directory/SAML 2.0/OAuth 2.0).
3. The system SHALL enforce Multi-Factor Authentication (MFA/2FA) for all roles above Student (R02), and optionally for students.
4. The login page SHALL display the university logo, system name, and a language selector (Arabic/English).
5. The system SHALL lock the account after 5 consecutive failed login attempts for 30 minutes and notify the user via email.
6. The system SHALL implement CAPTCHA after 3 consecutive failed login attempts.
7. All login sessions SHALL use HTTPS/TLS 1.3 or higher.
8. The system SHALL generate a JWT or secure session token with a configurable expiration period (default: 60 minutes of inactivity).
9. The system SHALL log every login attempt (successful and failed) with IP address, user agent, timestamp, and geolocation.
10. The system SHALL support "Forgot Password" with email-based reset link (valid for 15 minutes, single-use).
11. Password policy SHALL enforce: minimum 12 characters, at least 1 uppercase, 1 lowercase, 1 digit, 1 special character, no previous 5 passwords.

#### 4.1.2 FR-SEC-002: Role-Based Access Control (RBAC)

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-SEC-002 |
| **Title** | Role-Based Access Control |
| **Priority** | Critical |
| **Description** | The system SHALL enforce role-based access control for all resources and operations as defined in Section 3.2. |

**Acceptance Criteria:**

1. Each user SHALL be assigned one or more roles from the defined role set (R01–R10).
2. All API endpoints and UI components SHALL validate user permissions before granting access.
3. Unauthorized access attempts SHALL be logged and trigger security alerts.
4. Role assignments SHALL only be modifiable by System Administrators (R09) and Super Administrators (R10).
5. The system SHALL support session management including concurrent session limits (configurable per role).
6. The system SHALL implement Cross-Site Request Forgery (CSRF) protection on all forms.
7. The system SHALL implement Content Security Policy (CSP) headers.
8. All user inputs SHALL be sanitized against SQL injection, XSS, and other OWASP Top 10 vulnerabilities.
9. The system SHALL implement rate limiting on all API endpoints.
10. The system SHALL maintain a complete audit trail for all CRUD operations on sensitive data.

#### 4.1.3 FR-SEC-003: Session Management

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-SEC-003 |
| **Title** | Session Management |
| **Priority** | Critical |

**Acceptance Criteria:**

1. Sessions SHALL expire after configurable inactivity timeout (default: 60 minutes).
2. The system SHALL provide a "Remember Me" option valid for 7 days (configurable), which requires re-authentication for sensitive operations.
3. Users SHALL be able to view and terminate active sessions from their profile.
4. Administrative roles SHALL have the ability to force-terminate any user session.
5. Session tokens SHALL be securely stored (HttpOnly, Secure, SameSite cookies or encrypted local storage for mobile).

#### 4.1.4 FR-SEC-004: Data Encryption

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-SEC-004 |
| **Title** | Data Encryption |
| **Priority** | Critical |

**Acceptance Criteria:**

1. All data in transit SHALL be encrypted using TLS 1.3.
2. All sensitive data at rest SHALL be encrypted using AES-256.
3. Personally Identifiable Information (PII) SHALL be encrypted in the database.
4. File uploads (certificates, documents) SHALL be encrypted at rest and scanned for malware upon upload.
5. Database backups SHALL be encrypted.
6. Encryption keys SHALL be managed via a dedicated Key Management System (KMS).

---

### 4.2 Applicant Registration & Admission Module

#### 4.2.1 FR-ADM-001: Multi-Step Application Form

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-ADM-001 |
| **Title** | Multi-Step Applicant Registration Form |
| **Priority** | Critical |
| **Description** | The system SHALL provide a multi-step registration form for new applicants to submit their postgraduate application. |

**Step 1: Personal Information Form**

| Field | Type | Validation | Required |
|-------|------|-----------|:--------:|
| Full Name (English) | Text | Alphabetic, 2–100 chars | ✓ |
| Full Name (Arabic) | Text | Arabic characters, 2–100 chars | ✓ |
| Email Address | Email | Valid email format, unique in system | ✓ |
| Phone Number | Phone | International format with country code | ✓ |
| National ID / Passport Number | Text | Alphanumeric, validated per country | ✓ |
| Date of Birth | Date | Must be at least 21 years old | ✓ |
| Nationality | Dropdown | From predefined country list | ✓ |
| Gender | Radio | Male / Female | ✓ |
| Address (Country, City, Street) | Text/Dropdown | Country dropdown, city/street text | ✓ |
| Previous University Name | Text | 2–200 chars | ✓ |
| Graduation Certificate Name | Text | 2–200 chars | ✓ |
| Graduation Date | Date | Must be in the past | ✓ |
| Previous Degree (BSc / MSc) | Dropdown | BSc, MSc | ✓ |
| Previous Major/Specialization | Text | 2–200 chars | ✓ |
| Graduation GPA | Decimal | 0.00 – 4.00 (or percentage with conversion) | ✓ |
| Graduation CGPA | Decimal | 0.00 – 4.00 (or percentage with conversion) | ✓ |
| GPA Scale | Dropdown | 4.0, 5.0, Percentage | ✓ |
| Is E-JUST Graduate? | Checkbox | Boolean | ✓ |
| Previous E-JUST Program | Program selector | Required when Is E-JUST Graduate is true | Conditional |
| Applying for Degree | Dropdown | MSc, PhD | ✓ |
| English Proficiency Test Type | Dropdown | IELTS, TOEFL iBT, None | ✓ |
| English Proficiency Score | Decimal | Conditional on test type | Conditional |
| Native Arabic Speaker | Checkbox | Boolean | ✓ |

**Additional Acceptance Criteria:**

2. The system SHALL save form progress and allow the applicant to resume later.
3. All fields SHALL display inline validation errors in real-time.
4. The form SHALL be fully responsive (desktop, tablet, mobile).
5. The system SHALL prevent duplicate applications using email + national ID combination.

#### 4.2.2 FR-ADM-002: Email Verification

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-ADM-002 |
| **Title** | Email Verification |
| **Priority** | Critical |

**Acceptance Criteria:**

1. Upon submission of Step 1, the system SHALL send a verification email to the applicant's provided email address.
2. The verification email SHALL contain a unique, time-limited verification link (valid for 24 hours).
3. The applicant SHALL NOT proceed to Step 3 until email is verified.
4. The system SHALL allow re-sending the verification email (maximum 5 times per 24-hour period).
5. Verification links SHALL be single-use and cryptographically secure.
6. The system SHALL display a clear message indicating verification status.

#### 4.2.3 FR-ADM-003: Document Upload

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-ADM-003 |
| **Title** | Certificate and Document Upload |
| **Priority** | Critical |

**Required Documents:**

| Document | Format | Max Size | Required | Notes |
|----------|--------|:--------:|:--------:|-------|
| Graduation Certificate | PDF/JPG/PNG | 10 MB | ✓ | Must be certified/attested copy |
| Academic Transcript | PDF | 10 MB | ✓ | Official transcript from previous institution |
| IELTS/TOEFL Certificate | PDF/JPG/PNG | 10 MB | Cond. | Required if English proficiency test selected |
| National ID / Passport Copy | PDF/JPG/PNG | 5 MB | ✓ | Clear, valid, not expired |
| Personal Photo | JPG/PNG | 5 MB | ✓ | Recent, passport-style |
| CV / Resume | PDF | 10 MB | Optional | |
| Letter of Recommendation (x2) | PDF | 10 MB each | Optional | May be required per program |
| MSc Thesis (for PhD applicants) | PDF | 50 MB | Cond. | Required for PhD applications |
| Additional Certificates | PDF/JPG/PNG | 10 MB each | Optional | Up to 5 additional documents |

**Additional Acceptance Criteria:**

2. The system SHALL validate file type, size, and scan for malware/viruses before acceptance.
3. Each uploaded file SHALL be stored with encryption at rest.
4. The system SHALL display upload progress indicators.
5. The system SHALL allow the applicant to preview, replace, or remove uploaded documents before final submission.
6. The system SHALL generate a unique file identifier for each upload linked to the applicant's record.

#### 4.2.4 FR-ADM-004: Program Selection

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-ADM-004 |
| **Title** | Program Selection |
| **Priority** | Critical |

**Acceptance Criteria:**

1. The system SHALL display all available postgraduate programs grouped by Faculty (FoE, BAS, CSIT) in a tree-view structure.
2. Each program listing SHALL display: Program name (Arabic and English), Degree level (MSc / PhD), Department, Faculty, Total required credit hours, Available specializations/tracks, Program description, Admission requirements summary.
3. The applicant SHALL select one primary program and optionally one alternative program.
4. The system SHALL validate that the applicant's previous degree qualifies for the selected program.
5. The system SHALL display a confirmation summary of the selected program(s) before proceeding.

#### 4.2.5 FR-ADM-005: Student Affairs Screening

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-ADM-005 |
| **Title** | Student Affairs Application Screening |
| **Priority** | Critical |

**Acceptance Criteria:**

1. Upon final submission, the application SHALL be queued for Student Affairs staff review.
2. Student Affairs staff SHALL be able to: View complete application details and uploaded documents, Verify document authenticity status, Check application completeness, Add screening notes/comments, Mark application as: Approved for Dean Review / Rejected / Returned for Revision.
3. If returned for revision, the applicant SHALL receive an email notification with specific instructions on what needs correction.
4. The system SHALL enforce a configurable SLA for screening (default: 5 business days).
5. The system SHALL display pending application count and aging on the Student Affairs dashboard.

#### 4.2.6 FR-ADM-006: Dean Screening & Approval

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-ADM-006 |
| **Title** | Dean of Faculty Application Review |
| **Priority** | Critical |

**Acceptance Criteria:**

1. Applications approved by Student Affairs SHALL be forwarded to the Dean of the relevant Faculty.
2. The Dean SHALL be able to: View complete application with Student Affairs notes, Approve for interview scheduling, Reject with reason, Request additional information from the applicant or Student Affairs.
3. The system SHALL track Dean review timestamps and decisions.
4. The system SHALL enforce a configurable SLA for Dean review (default: 5 business days).

#### 4.2.7 FR-ADM-007: Interview Scheduling

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-ADM-007 |
| **Title** | Interview Calendar & Scheduling |
| **Priority** | High |

**Acceptance Criteria:**

1. Upon Dean approval, the system SHALL display available interview slots from a configurable academic calendar.
2. The system SHALL allow Student Affairs or designated staff to: Set available interview dates and time slots, Assign interviewers (faculty members), Set interview location (physical or virtual with meeting link).
3. The applicant SHALL receive an email notification with interview details.
4. The applicant SHALL be able to confirm or request one reschedule (within available slots).
5. The system SHALL send reminder emails 48 hours and 24 hours before the interview.
6. Interviewers SHALL be able to enter interview evaluation scores and notes.

#### 4.2.8 FR-ADM-008: Final Admission Decision

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-ADM-008 |
| **Title** | Admission Decision & Notification |
| **Priority** | Critical |

**Acceptance Criteria:**

1. After the interview, the system SHALL allow authorized personnel to record the final decision: Accepted (with assigned program, academic advisor, and enrollment semester), Conditionally Accepted (with conditions to be met before enrollment), Waitlisted (with waitlist position), Rejected (with reason code).
2. Upon acceptance, the system SHALL: Transition the applicant role from Applicant (R01) to Student (R02), Generate a unique University ID, Assign an academic advisor, Create the student's academic record, Send an acceptance email with enrollment instructions, Automatically apply applicable exemptions.
3. Upon rejection, the system SHALL send a notification email with the reason.
4. The system SHALL maintain a complete audit trail of all admission decisions.

#### 4.2.9 FR-ADM-009: Application Status Tracking

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-ADM-009 |
| **Title** | Application Status Tracking |
| **Priority** | High |

**Application Status Types:**

| Status | Description |
|--------|-------------|
| Draft | Application started but not submitted |
| Submitted | Application submitted, pending email verification |
| Email Verified | Email verified, documents under review |
| Under SA Review | Student Affairs screening in progress |
| SA Approved | Student Affairs approved, forwarded to Dean |
| Returned for Revision | Application returned with comments |
| Under Dean Review | Dean screening in progress |
| Interview Scheduled | Interview date assigned |
| Interview Completed | Interview conducted, awaiting decision |
| Accepted | Admitted to the program |
| Conditionally Accepted | Accepted with conditions |
| Waitlisted | On waiting list |
| Rejected | Application rejected |

**Additional Acceptance Criteria:**

2. Each status change SHALL be timestamped and visible to the applicant.
3. The system SHALL display a visual progress bar/timeline of the application stages.

---

### 4.3 Program & Course Management Module

#### 4.3.1 FR-PCM-001: Program Management

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-PCM-001 |
| **Title** | Program Creation and Management |
| **Priority** | Critical |

**Acceptance Criteria:**

1. The system SHALL allow authorized users (System Admin, Dean, Dept. Head) to create, edit, activate, and deactivate academic programs.
2. Each program SHALL have attributes including: Program ID, Code, Name (EN/AR), Faculty, Department, Degree Level, Total Required CH, etc.
3. The system SHALL maintain version history for all program changes.
4. Deactivating a program SHALL NOT affect currently enrolled students.

#### 4.3.2 FR-PCM-002: Program Tree View

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-PCM-002 |
| **Title** | Program Tree View Display |
| **Priority** | High |

**Acceptance Criteria:**

1. The system SHALL provide an interactive tree-view display with the following hierarchy:

```
University
├── Faculty of Engineering (FoE)
│   ├── Department of Computer Engineering
│   │   ├── MSc in Computer Engineering
│   │   │   ├── Preparatory Courses (6 CH)
│   │   │   │   ├── PRE401 - English Language (1 CH)
│   │   │   │   ├── PRE402 - Arabic Language (1 CH)
│   │   │   │   ├── PRE403 - Japanese Language (1 CH)
│   │   │   │   ├── PRE404 - Japanese Culture (1 CH)
│   │   │   │   ├── DEP501 - Key Skills / Dept. Req. 1 (1 CH)
│   │   │   │   └── DEP502 - Dept. Req. 2 (1 CH)
│   │   │   ├── Program Core Courses (6 CH)
│   │   │   └── Research Elective Courses (9 CH)
│   │   └── PhD in Computer Engineering
│   └── Department of Electrical Engineering
├── Faculty of Basic & Applied Sciences (BAS)
└── Faculty of CSIT
```

2. Each node in the tree SHALL be expandable/collapsible.
3. Clicking on a course node SHALL display full course details.
4. The tree view SHALL support search and filtering.
5. The tree view SHALL indicate course status with visual indicators.

#### 4.3.3 FR-PCM-003: Course Management

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-PCM-003 |
| **Title** | Course Creation and Management |
| **Priority** | Critical |

**Acceptance Criteria:**

1. The system SHALL allow authorized users to create, edit, activate, and deactivate courses.
2. The system SHALL validate that Lecture CH + Tutorial CH + Lab CH = Total Credit Hours.
3. The system SHALL prevent deletion of courses that have enrollment history.
4. The system SHALL maintain version history for all course changes.
5. The system SHALL support bulk import/export of courses via CSV/Excel.
6. On admission, a Student SHALL be bound to the Program Curriculum version effective at
   that time. Later Program or Curriculum edits SHALL NOT implicitly change that Student's
   requirements, classifications, or credit accounting.
7. After a Course activation-state change, Catalog SHALL publish a Public `CourseChanged`
   event containing only the Course ID. Consumers SHALL re-read authoritative Catalog data
   rather than rely on copied Course fields.
7. Existing Students SHALL be backfilled to the latest Program Version at or before their
   admission timestamp. If none predates admission, the earliest available Version SHALL be
   used and the fallback SHALL be identified for audit and review.

---

### 4.4 Student Course Registration Module

#### 4.4.1 FR-REG-001: First-Year Registration

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-REG-001 |
| **Title** | First-Year Course Registration |
| **Priority** | Critical |
| **Description** | The system SHALL manage first-year registration including preparatory courses and program core courses with automatic exemption application. |

**Automatic Exemption Rules:**

| Condition | Automatic Exemption | CH |
|-----------|--------------------|----|
| IELTS ≥ 6.5 OR TOEFL iBT ≥ 79 | PRE401 (English Language) | 1 |
| PhD student who completed an MSc at E-JUST in the same Department | All 6 preparatory courses | 6 |
| E-JUST BSc graduate | PRE403, PRE404, Key Skills | 3 |

Maximum exempted credit hours from preparatory courses: **6 credit hours**.

The system SHALL resolve each named automatic Exemption through the selected Program's
Preparatory Requirement Roles rather than hard-coded Course ids. Every Program SHALL have
exactly one Preparatory Curriculum Entry for each of the six roles: English Language,
Arabic Language, Japanese Language, Japanese Culture, Key Skills, and Departmental
Requirement.

A Student is Approaching Graduation only when total Remaining CH is greater than zero and
less than 6, and the proposed for-credit schedule satisfies every remaining Course target
or category slot. Audit Courses do not satisfy requirements. The system SHALL allow that
completing schedule below the normal minimum; a Student with 6 or more Remaining CH may not
use the exception by selecting only part of the remaining work.

**Credit Hour Limits per Semester:**

| Case | Min CH | Max CH | System Behavior |
|------|:------:|:------:|-----------------|
| Regular student | 6 | 12 | Block registration below 6 or above 12 CH |
| Student approaching graduation | Remaining | 12 | Allow below 6 CH only for a schedule that satisfies every remaining requirement |
| First semester – exempted | Remaining non-exempt Preparatory load | 12 | Allow 1–5 CH only when every remaining Preparatory requirement is on the schedule |

The first-semester exception applies only in the Student's enrollment Semester and only
when the post-change schedule includes every Preparatory Course not already satisfied or
exempted. The resulting load may be 1–5 CH. If no Preparatory requirements remain, the
normal 6-CH floor applies; an Exemption does not create a general low-load privilege, and
Audit Courses do not satisfy the condition.

#### 4.4.2 FR-REG-002: Coursework Registration

| Attribute | Detail |
|-----------|--------|
| **ID** | FR-REG-002 |
| **Title** | Coursework Registration (Core + Elective) |
| **Priority** | Critical |

**Program Core Courses:**

1. The system SHALL require students to register for 2 program core courses (3 CH each = 6 CH total).
2. Core courses MUST be from the student's enrolled program.

**Research Elective Courses:**

3. The system SHALL require students to register for 3 research elective courses (3 CH each = 9 CH total).

| # | Course Type | Level (MSc) | Level (PhD) | CH | Constraints |
|---|------------|:-----------:|:-----------:|:--:|-------------|
| 1 | Internal elective | 500 | 600 | 3 | Must be from student's own program |
| 2 | External elective | 500 | 600 | 3 | From any graduate program, requires advisor approval |
| 3 | Research course (PBL/Seminar) | 700 | 700 | 3 | PBL for MSc, Seminar for PhD |

#### 4.4.3 FR-REG-003: Course Registration Workflow

**Acceptance Criteria:**

1. The student SHALL select courses from the eligible course list and submit a registration request.
2. The Registration Request SHALL enter `Pending Advisor` and be sent to the assigned
   Academic Advisor for approval.
3. The Academic Advisor SHALL be able to approve, reject, or modify the registration. If
   approval requires the full Must-Retake Offering exception in FR-REG-006, Advisor
   recommendation SHALL instead move it to `Awaiting Capacity Override` for the scoped
   Department Head's final decision.
4. Registration approval status SHALL be displayed as: Pending Advisor / Awaiting Capacity
   Override / Approved / Rejected. The ordinary path is Pending Advisor → Approved or
   Rejected; the exception path is Pending Advisor → Awaiting Capacity Override → Approved
   or Rejected. Only Approved SHALL atomically create Enrollments.
5. The system SHALL send email notifications at each status change.

#### 4.4.4 FR-REG-004: Course Add/Drop/Withdrawal

| Period | Action Allowed | Grade Recorded | System Behavior |
|--------|---------------|:--------------:|-----------------|
| Add/Drop (Week 1-2) | Add or drop freely | No grade | Full refund |
| Late Add (Week 3) | Add with approval | No grade | Partial refund |
| Withdrawal (Week 4–X) | Withdraw | "W" recorded | No refund |
| After Deadline | No withdrawal | Regular grade or FW | Must complete |

#### 4.4.5 FR-REG-005: Course Status Management

| Status | Transcript | CGPA | Req. CH | Attend. CH | Earned CH | Rules |
|--------|:----------:|:----:|:-------:|:----------:|:---------:|-------|
| Regular | ✓ | ✓ | ✓ | ✓ | ✓ | Standard enrollment |
| Retake | ✓ | ✓(Last) | ✓ | ✓ | ✓(Last) | Max 1 retake per course |
| Audit (AU) | ✓ | ✗ | ✗ | ✓ | ✗ | Must attend ≥75% |
| Withdrawal (W) | ✓ | ✗ | ✗ | ✗ | ✗ | Within withdrawal period |
| CGPA Improve | ✓ | ✓(Last) | ✓ | ✓ | ✓(Last) | Max 1 per course |
| FW | ✓ | ✗ | ✗ | ✗ | ✗ | After deadline |
| Incomplete (I) | ✓ | ✗* | ✗* | ✓ | ✗* | ≥70% completed |

> \*until resolved
>
> Exemptions, Transfers, and Waivers are standalone, course-targeted decisions rather
> than Enrollments or Course Statuses; their accounting rules are in Appendix D.

#### 4.4.6 FR-REG-006: Re-Registration Rules

**Acceptance Criteria:**

1. A student SHALL be allowed to re-register for a course maximum once.
2. The latest grade SHALL replace the previous grade in CGPA calculation.
3. Both attempts SHALL appear on the transcript.
4. **Elective Courses:** If below C+ (2.5), the Student MAY register for a different
   elective and all Grades count in CGPA. Passing the different Course MAY satisfy the
   curriculum slot but SHALL NOT supersede the original Course's Grade; that Grade remains
   effective for Academic Warning and D/F termination evaluation until a successful later
   Attempt in the same Course supersedes it.
5. **Preparatory and Core Courses:** If below C+ (2.5), the student MUST retake the same
   Course and only the latest Grade counts in CGPA. In a later Semester, eligible
   Offerings for outstanding Must-Retake Obligations take priority over unrelated
   for-credit Courses. If all offered obligations fit within the Student's dynamic load
   ceiling, the post-change schedule SHALL include all of them before unrelated
   for-credit Courses. If they exceed the ceiling, the schedule SHALL contain only a
   feasible selection of those obligations up to the ceiling. Load or timetable conflicts
   SHALL resolve deterministically: Preparatory obligations first, ordered by earliest
   applicable termination/remediation checkpoint, then origin time (effective failed Grade
   or Exemption Revocation), then Course code; Core obligations follow, ordered by oldest
   failed Attempt then Course code. The validator SHALL walk that order
   once and include a Course only when an eligible Offering fits the remaining load and
   timetable; it SHALL NOT skip a feasible higher-priority Course to maximize Course
   count, credit hours, or load utilization. The Student and Advisor SHALL NOT substitute
   a lower-priority obligation. This selects required Courses, not exact sections: for
   each required Course the Student MAY choose any eligible, non-conflicting Offering, and
   the Advisor MAY change that Offering without changing the required Course. If none is
   offered, other registration MAY proceed. Audit Enrollment SHALL neither satisfy nor
   clear the rule.
6. Whether a Must-Retake Course is offered SHALL be determined independently of unrelated
   Courses already chosen by the Student. During a registration or modification window,
   a conflicting unrelated Course SHALL be removed or switched so an eligible Offering
   of the required Course can be selected. A conflict between required Courses SHALL use
   the deterministic priority in item 5. After all applicable modification windows close,
   the system SHALL NOT automatically drop an Enrollment; an unselected obligation SHALL
   carry forward.
7. A Must-Retake Course SHALL remain available for priority purposes when its eligible
   Offering is at capacity. After the Academic Advisor recommends the registration, the
   Department Head scoped to that Course's Department MAY give final approval to exceed
   capacity for the required retake only. The override SHALL require a reason and an Audit
   Log entry, SHALL be revalidated at approval, and SHALL NOT displace an existing
   Enrollment. It SHALL be authorized by the Department Head Role plus scope to the
   Course's Department, not by the ordinary Course Registration approval Permission; it
   SHALL NOT expose the general registration queue or add a new Permission. Ordinary
   registration SHALL NOT use this exception.
8. When one Registration Request includes full Must-Retake Offerings owned by different
   Departments, each owning Department Head SHALL record an independent decision for
   their Offering and SHALL NOT decide another Department's capacity. The request SHALL
   remain `Awaiting Capacity Override` until every required decision approves. Any denial
   SHALL reject the unchanged atomic Registration Request; the system SHALL NOT silently
   remove that mandatory line. Enrollments SHALL be created atomically only after all
   required approvals exist and final validation passes.
9. Capacity changes affecting an `Awaiting Capacity Override` request SHALL be reconciled
   idempotently. If an Offering gains normal capacity, its active override requirement
   SHALL cease without deleting any recorded decision. If no active requirement remains,
   the system SHALL transactionally revalidate the Advisor-recommended batch and approve
   it automatically only when every line fits normal capacity and every other rule still
   passes. If a requested Offering that previously had capacity becomes full while the
   request waits, reconciliation SHALL add a per-Offering override requirement routed to
   that Course's scoped Department Head before final approval. An approval already
   recorded for the same request and Offering SHALL remain in history and SHALL NOT be
   duplicated. The system SHALL NOT reserve the newly available seat before that
   transaction.
10. A Preparatory requirement inside a post-revocation Remediation Window SHALL create a
    distinct Remediation Obligation even when no prior Attempt exists. While active, it
    SHALL share the Must-Retake validator's deadline-first Preparatory Course priority,
    timetable treatment, and full-Offering Department Head capacity-exception workflow. It
    SHALL end when the Course satisfies the requirement, a replacement Preparatory
    Exemption closes it as `Released By Exemption`, or its remediation checkpoint is
    evaluated; if still unsatisfied at that checkpoint, it SHALL become Preparatory
    termination evidence. `Released By Exemption` SHALL record corrected applicability
    evidence and SHALL NOT claim Course completion.
11. If no future eligible Offering is scheduled, the Remediation Obligation SHALL remain
    `Awaiting Offering` with no termination checkpoint. It SHALL contribute to Remaining
    CH and block Graduation Readiness but SHALL NOT create termination evidence. The system
    SHALL notify the Student, assigned Advisor, and scoped Department Head once on entering
    that state. When a real enrollment opportunity is scheduled, idempotent reconciliation
    SHALL assign the checkpoint, activate registration priority, and notify those actors.
    Enrollment SHALL reconcile immediately after relevant local Course Offering changes
    and consume Calendar's Public `SemesterChanged` event for relevant Semester-date
    changes. The event SHALL identify the Semester rather than duplicate its dates;
    Enrollment SHALL read authoritative Calendar data. Before checkpoint evaluation, a
    later Grade-Submission Deadline SHALL extend the active checkpoint, while an earlier
    deadline SHALL NOT shorten time already granted. Either edit SHALL append an auditable
    Calendar-deadline-observed transition recording the official date and effective
    checkpoint and SHALL notify the Student, assigned Advisor, and scoped Department Head.
    After evaluation, the deadline used by that decision SHALL remain frozen. An idempotent
    `enrollment:reconcile-remediation {student?}` command SHALL recover missed post-commit
    handling globally or for one Student.
12. When replacement Preparatory Exemption approval releases a Remediation Obligation, any
    `Pending Advisor` or `Awaiting Capacity Override` Registration Request containing that
    Course SHALL be terminally rejected in the same Enrollment transaction. The system
    SHALL append a system-attributed reason, preserve all request and per-Offering capacity
    review history, and notify the Student and current registration reviewers. It SHALL NOT
    silently remove the Course or approve the remainder; the Student SHALL submit a newly
    validated batch.
13. If that Course has already become an in-progress Enrollment, replacement Exemption
    approval SHALL NOT automatically drop, withdraw, or convert it. The Student MAY use the
    ordinary drop or withdrawal workflow while available; otherwise the Enrollment SHALL
    continue through normal attendance and grading. Its eventual Attempt and Grade SHALL
    remain in history and CGPA. Raw Earned and Exempted CH MAY coexist, but the Course's
    Earned CH SHALL be excluded from the target-matched Satisfied operand while its
    Exemption is effective; the category cap SHALL then apply.
14. Before the Student enrolls in the restored Course, an institution-controlled Course,
    Offering, schedule, or Calendar change that removes the qualifying opportunity SHALL
    trigger reconciliation. The system SHALL prefer another eligible Offering in the same
    Semester; otherwise it SHALL assign the earliest later lawful opportunity, or return
    the obligation to `Awaiting Offering` with no checkpoint. It SHALL append the old and
    new opportunity/checkpoint evidence and notify the Student, assigned Advisor, and
    scoped Department Head once per real transition. Full capacity and Student-created
    timetable conflicts SHALL NOT withdraw an opportunity. After an Enrollment exists, the
    opportunity SHALL count as used and SHALL NOT be reassigned.
    Enrollment SHALL consume Catalog's Public ID-only `CourseChanged` event and run the
    same idempotent reconciliation service used by local Offering changes, Calendar's
    `SemesterChanged` event, and `enrollment:reconcile-remediation {student?}`.

---

### 4.5 Exemption Management Module

#### Shared Manual-Request Lifecycle

1. A manual Exemption, Transfer, or Waiver request SHALL begin as an editable Draft in
   which the Student can add, replace, or remove supporting evidence.
2. Submission SHALL lock the request and its evidence while it is under review.
3. At each applicable review stage, the reviewer SHALL be able to advance the request,
   reject it terminally with a reason, or return it to the Student for revision with
   instructions.
4. A returned request SHALL become editable. Resubmission SHALL restart review from the
   Advisor and invalidate all earlier recommendations or verifications because the
   evidence may have changed.
5. Every transition SHALL be recorded in an append-only history with its actor, timestamp,
   and optional note. Rejected and Approved are terminal states.
6. Final approval SHALL atomically create a separate immutable Exemption, Transfer, or
   Waiver decision linked to its source request. Draft, returned, or rejected requests
   SHALL never affect academic accounting. Automatic Exemptions create decisions directly
   and have no synthetic Student request.
7. An effective decision SHALL NOT be edited or deleted. Its final authority may append a
   Revocation with a required reason: the Department Head for an Exemption, or the Education
   & Academic Affairs Council for a Transfer or Waiver. A replacement requires a new
   request and full review. The original decision and Revocation remain visible in history.
   If a Preparatory Exemption is revoked after the Student's ordinary two-Semester
   completion checkpoint, the restored requirement SHALL receive a Remediation Window
   through the Grade-Submission Deadline of the first main Semester in which that Course
   has an eligible Offering and the Student can still lawfully add it at Revocation time.
   The current Semester SHALL qualify while registration, add/drop, or late-add remains
   open; after late-add closes, the search SHALL move forward. The Revocation SHALL NOT
   immediately create a termination trigger.
8. Corrected accepted evidence may trigger explicit automatic-Exemption reconciliation,
   which appends any necessary Revocation and creates any newly justified decision
   idempotently; it SHALL NOT silently rewrite history.
9. A new Transfer or Waiver request SHALL NOT target a Course in which the Student already
   has an effective for-credit Attempt that failed or earned credit. A corrected Exemption
   remains eligible after either a failed or successful Attempt because it proves that a
   Preparatory requirement did not apply; if approved, it releases the requirement without
   removing the Attempt, Grade, raw Earned CH, or CGPA effect. While effective, the
   Exemption SHALL exclude that Course's Earned CH from target-matched Satisfied CH.
10. If a replacement Preparatory Exemption is approved while its post-Revocation
    Remediation Obligation is active or `Awaiting Offering`, approval SHALL atomically close
    that obligation as `Released By Exemption`. The resulting academic-change event SHALL
    cause normal AcademicStanding reconciliation to clear matching evidence from any
    non-final Termination Case. It SHALL NOT reverse a final termination.
11. When finalizing an initial or correction Grade creates an effective failed Attempt in
    the target Course of a submitted Transfer or Waiver request, the system SHALL
    terminally reject that request in an append-only transition with the Grade event as
    its cause. It SHALL notify the Student and the reviewers currently involved. Repeated
    delivery of the same Grade event SHALL be idempotent.

#### 4.5.1 FR-EXM-001: Course Exemption Request and Processing

**Automatic Exemptions:**

1. On admission acceptance, the system SHALL idempotently create approved Exemption
   records from the accepted Application's qualifying evidence, without requiring a
   Student request and before the Student's first course registration.
2. Maximum exempted credit hours from preparatory courses: 6 CH.

**Manual Exemptions:**

3. Students SHALL be able to submit an Exemption request when evidence for an automatic
   Preparatory exemption rule was missing, unverified, or incorrect during admission.
4. MSc-at-E-JUST → PhD in the same Department remains an Exemption from all six
   Preparatory Courses. The previous E-JUST Program SHALL be captured as a structured
   Catalog Program reference; free-text major matching SHALL NOT decide eligibility.
   Generic prior-course equivalence SHALL be processed as a Waiver under FR-TRW-002, not
   as an Exemption.
5. Approval workflow: Student submits → Academic Advisor recommends → Department Head
   approves or rejects. The Department Head represents the departmental review; no
   separate Department Council Role exists.
6. A corrected Exemption approved after a failed or successful Attempt SHALL release the exempted
   Preparatory requirement and any Must-Retake Obligation for it. The Attempt and Grade
   SHALL remain visible and SHALL continue to contribute to CGPA under the normal
   supersession rules. Raw Earned CH from a successful Attempt SHALL remain visible but
   SHALL NOT enter Satisfied CH while the Exemption for that Course is effective.
7. If the released Course has an existing in-progress Enrollment, approval SHALL leave it
   unchanged. The Exemption releases the requirement rather than undoing the academic
   commitment; ordinary drop, withdrawal, and grading rules SHALL continue to apply.

---

### 4.6 Course Transfer & Waiver Module

#### 4.6.1 FR-TRW-001: Course Transfer

**Acceptance Criteria:**

1. Maximum 9 credit hours transferable across all effective Transfer decisions. Each
   decision recognizes the mapped target Course's credit hours from the Student's bound
   Curriculum Version; source credit hours are retained as evidence but do not drive the
   cap or Credit-Hour Summary. Final approval SHALL revalidate the lifetime total
   transactionally, and a Revocation removes that decision from the active total.
2. The Student SHALL submit the external grade exactly as printed with the supporting
   transcript. During equivalency verification, the Department Head SHALL record its
   normalized E-JUST Letter Grade; only A+, A, B+, or B satisfies the minimum grade.
   The original and normalized values, verifier, and verification timestamp SHALL be
   retained. The normalized grade decides Transfer eligibility only and SHALL NOT enter
   GPA or CGPA.
3. Must have equivalent course in program.
4. Approval workflow: Student submits → Advisor recommends → Department Head verifies the
   course equivalency → Education & Academic Affairs Council approves or rejects.
5. Requests MUST be submitted within 1 month of the Student's admission-acceptance
   timestamp (`admitted_at`), regardless of when the enrollment Semester begins or the
   first Course Registration is approved. The deadline is inclusive and falls at the same
   time one calendar month later, clamped to the destination month's last valid day.
6. A new Transfer request SHALL be rejected when its target Course already has an
   effective failed for-credit Attempt by that Student.

#### 4.6.2 FR-TRW-002: Course Waiver

**Acceptance Criteria:**

1. Only a prior E-JUST Course at level 400 or above may support a Waiver. Study completed
   outside E-JUST SHALL be evaluated only through the Transfer workflow and SHALL NOT use
   a Waiver to bypass Transfer limits or deadlines.
2. The prior E-JUST Course SHALL have an effective Grade of C+ or higher. When its academic
   record is unavailable in PRS, the Student SHALL provide an official E-JUST transcript
   and the Department Head SHALL verify the equivalent passing result.
3. Minimum 80% content coverage is required and SHALL be recorded by the Department Head
   during verification.
4. Waived courses do NOT affect CGPA.
5. Appear on transcript as "Waived".
6. Approval workflow: Student submits → Advisor recommends → Department Head verifies the
   level and content coverage → Education & Academic Affairs Council approves or rejects.
7. A new Waiver request SHALL be rejected when its target Course already has an effective
   failed for-credit Attempt by that Student.

---

### 4.7 Grading & GPA Calculation Module

#### 4.7.1 FR-GRD-001: Grade Entry by Instructor

**Acceptance Criteria:**

1. Instructors (R04) SHALL be able to enter marks/grades for courses they teach.
2. The system SHALL auto-calculate letter grades based on the following scale:

| Letter Grade | GPA Points | Percentage Range | Description |
|:------------:|:----------:|:----------------:|-------------|
| A+ | 4.00 | 97–100 | Exceptional |
| A | 4.00 | 93–96.99 | Excellent |
| B+ | 3.30 | 87–89.99 | Very Good |
| B | 3.00 | 83–86.99 | Good+ |
| C+ | 2.50 | 77–79.99 | Satisfactory+ |
| C | 2.00 | 73–76.99 | Satisfactory |
| D+ | 1.30 | 67–69.99 | Below Average |
| D | 1.00 | 60–66.99 | Poor |
| F | 0.00 | Below 60 | Fail |
| P | – | – | Pass (Pass/Fail courses) |
| FL | – | – | Fail (Pass/Fail courses) |
| AU | – | – | Audit |
| W | – | – | Withdrawal |
| FW | – | – | Fail Withdrawal |
| I | – | – | Incomplete |

3. The instructor SHALL be able to sign/finalize grades.
4. After instructor signing, grades SHALL be forwarded to the Department Head for review.

#### 4.7.2 FR-GRD-002: GPA and CGPA Calculation

> *This section is intentionally left blank for GPA and CGPA calculation formulas and rules to be defined.*

#### 4.7.3 FR-GRD-003: Incomplete Grade Management

**Acceptance Criteria:**

1. An Incomplete (I) grade SHALL only be assigned if the student has completed ≥70% of the course.
2. The Incomplete grade MUST be resolved before the 10th week of the next semester.
3. If not resolved by deadline, the system SHALL automatically convert to FW.
4. The system SHALL send automated reminders at 4 weeks, 2 weeks, 1 week, and 1 day before deadline.

---

### 4.8 Academic Standing & Enrollment Termination Module

#### 4.8.1 FR-AST-001: Academic Standing Monitoring

| Standing | Criteria | System Action |
|----------|----------|---------------|
| Not Yet Assessed | No finalized CGPA-bearing Grade exists | Display as not yet assessed; no warning action |
| Good Standing | CGPA ≥ 3.0 and all grades ≥ C+ | No action required |
| Academic Warning | CGPA ≥ 3.0 and any effective grade < C+ | Display warning, notify student and advisor |
| Academic Probation | CGPA < 3.0 after any CGPA-bearing coursework is finalized | Cap Semester registration load at the configured minimum CH |
| Termination Pending | Meets actionable termination criteria | Trigger termination workflow and apply the Probation registration ceiling until the final decision |

The calculator applies the table in this precedence: Termination Pending → Not Yet
Assessed → Academic Probation → Academic Warning → Good Standing. Exactly one value SHALL
be returned. Termination is evaluated even when no CGPA-bearing Grade exists. “After
completing coursework” means after at least one CGPA-bearing Grade has been finalized, not
after all degree requirements have been satisfied. Only effective, non-superseded Grades
participate, and CGPA is the authoritative rounded value from Grading.

For a Student on Academic Probation, the configured minimum CH becomes the registration
ceiling as well as the ordinary floor (6 CH by default). Every load-counting mode,
including Audit, consumes that ceiling. The first-semester and Approaching Graduation
exceptions may still permit an eligible schedule below the floor; no exception permits a
load above the Probation ceiling.

Termination Pending applies the same registration ceiling and below-minimum exceptions
while the Student Lifecycle Status remains Active. It SHALL NOT block registration before
the Council's final decision; only final termination blocks new registration mutations.

Current Academic Standing SHALL remain derived on demand. After a relevant academic or
termination event, the system SHALL recalculate it and append a Standing Observation only
when the value differs from the latest Observation. Entering Warning, Probation, or
Termination Pending and moving to a less severe Standing SHALL notify the Student and
assigned Advisor once; the initial Not Yet Assessed Observation SHALL NOT notify. An
Observation is history and a notification checkpoint, never the source of current Standing.
Handlers and per-Student reconciliation SHALL be idempotent.

Satisfying an elective curriculum slot with a different Course SHALL NOT supersede the
original Course's effective below-C+ Grade. It remains part of Academic Warning and, when
D or F, termination evaluation, as well as CGPA. Only a successful later Attempt in that
same Course may supersede it.

Academic Warning by itself SHALL NOT block graduation readiness. A Student who has
satisfied every curriculum requirement and whose authoritative CGPA is at least 3.00
SHALL meet the academic readiness test despite the Warning. Good Standing SHALL NOT be
added as a separate graduation requirement.

Termination Pending SHALL temporarily block graduation readiness while its actionable
Termination Case remains open. A Department Head or Council dismissal, or an automatic
`Trigger Cleared` transition, SHALL remove that block immediately and restore the direct
curriculum-satisfaction plus CGPA test.

Graduation Readiness SHALL be a derived advisory result only. It SHALL NOT automatically
graduate the Student, change Student Lifecycle Status, or stand in for a formal graduation
decision and its non-course or administrative checks.

The Graduation Readiness contract SHALL return the authoritative `ready` boolean,
Remaining CH, nullable CGPA, the separate Approaching Graduation result, and zero or more
typed blockers from this closed set: `RemainingRequirements`, `CgpaUnavailable`,
`CgpaBelowMinimum`, and `TerminationPending`. API, UI, Card, Report, and export consumers
SHALL render these fields and SHALL NOT independently reconstruct readiness.

Approaching Graduation SHALL remain independent of CGPA and Termination Pending. It SHALL
be true solely when Remaining CH is 1–5 and the proposed for-credit schedule satisfies
every remaining requirement. CGPA and termination blockers SHALL be displayed alongside
that result and SHALL NOT suppress the below-minimum completing schedule allowed during
Probation or termination due process.

#### 4.8.2 FR-AST-002: Enrollment Termination

**Termination Criteria:**

| # | Criterion | Details |
|---|-----------|---------|
| 1 | Failure to complete Preparatory requirements | After the Grade-Submission Deadline of the second consecutive main Semester beginning with the enrollment Semester, any Adjusted Preparatory requirement remains unsatisfied; a requirement restored by a later Exemption Revocation instead becomes actionable after the Grade-Submission Deadline of the first main Semester in which its Course has an eligible Offering and the Student can still add it at Revocation time (current Semester only through late-add); an unresolved Incomplete at either checkpoint defers determination until resolution or expiry, and English is one Preparatory Requirement Role rather than an additional condition |
| 2 | Effective Grade D in 2+ Courses | Two or more distinct Courses have a current effective, non-superseded Grade of D or F; D+ does not qualify, two Attempts of one Course count once, a successful later Attempt in the same Course clears it, and passing a different elective does not |

**Termination Workflow:**

System Flags → Student & Advisor Notified → Advisor Assesses → Department Head Recommends
or Dismisses → Education & Academic Affairs Council Terminates or Dismisses → Student and
Advisor Notified

Each Student SHALL have at most one non-terminal Termination Case. That Case SHALL aggregate
the complete set of currently actionable Preparatory and D/F trigger components after
excluding dismissed fingerprints. Creation, terminal transition, and `Evidence Changed`
replacement SHALL preserve this invariant atomically and idempotently under concurrent
events or command runs; the system SHALL NOT create parallel trigger-specific workflows.

The shared `termination.recommend` Permission authorizes different transitions by Role:
the assigned Advisor may record only the assessment, while the Department Head scoped to
the Student's bound Program Department may record only the departmental recommendation or
dismissal. A Case cannot reach the Council without both stages.

While a Case awaits Advisor assessment, the system SHALL resolve the Student's assigned
Advisor at action time. Reassignment SHALL remove the Case from the former Advisor's queue
and authority and place it with the new Advisor. An assessment recorded before reassignment
SHALL remain immutable, attributed to its actor, and valid; the Case SHALL continue at its
current later stage without restarting. Later Advisor notifications SHALL target the
currently assigned Advisor.

Final Council termination and replacement Preparatory Exemption approval SHALL serialize
on one transaction-scoped guard for the Identity-owned Student anchor, acquired before any
module-owned lock. Each workflow SHALL revalidate lifecycle and current authoritative
termination evidence after acquiring it. If Exemption approval commits first, finalization
SHALL observe the cleared trigger and append `Trigger Cleared` rather than terminate on
stale evidence. If termination commits first, Exemption approval SHALL fail without any
decision, obligation, Registration Request, or notification change.

Before every workflow action and after any relevant Grade, Incomplete, Exemption, Transfer,
Waiver, or Revocation change, the system SHALL recompute the Case's derived triggers. If no
trigger remains before the Council's final decision, the system SHALL append a terminal
`Trigger Cleared` transition, block further review, and notify the Student and Advisor. A
later trigger SHALL open a new Case. Remediation after a final termination SHALL NOT
automatically reinstate the Student.

If a non-terminal Case's actionable fingerprint changes while at least one trigger remains,
the system SHALL atomically close that Case as `Evidence Changed` and open a new Case at the
Advisor-assessment stage with the complete current fingerprint. Earlier reviews SHALL
remain immutable history and SHALL NOT transfer to the new Case. `Evidence Changed` SHALL
NOT suppress either fingerprint; if the former evidence later becomes current, it MAY open
a Case. The new Case SHALL send the normal initial Student and Advisor notification. If the
derived Standing remains Termination Pending, the system SHALL NOT append a duplicate
Standing Observation.

Because the localhost build has no scheduler, an idempotent, manually run
`academic-standing:reconcile {student?}` command SHALL use the same per-Student service as
the event handlers. Without an argument it SHALL inspect every active Student; with a
Student argument it SHALL perform targeted recovery. It SHALL evaluate elapsed ordinary and
remediation checkpoints, open a missing Termination Case or append `Trigger Cleared` when
appropriate, and append any missing Standing Observation and once-only notifications.
It SHALL read but SHALL NOT convert Grading-owned Incomplete outcomes. Standing and
Graduation Readiness GET requests SHALL remain side-effect free.

For a determinate remediation checkpoint, AcademicStanding SHALL atomically create or
reconcile the Termination Case and Standing Observation and invoke Enrollment's idempotent
Public evaluation command with the Remediation Obligation id and expected effective
deadline. Enrollment SHALL remain the obligation's only writer. An already evaluated
matching checkpoint SHALL be a successful no-op; an active deadline mismatch SHALL roll
back every module's changes so reconciliation can reload after a concurrent Calendar
extension. A valid unresolved Incomplete SHALL defer determination and leave the obligation
active.

Each Case SHALL retain a canonical, deterministically ordered fingerprint of the evidence
that opened it. A Preparatory component SHALL contain the requirement ID, its checkpoint
source (ordinary checkpoint identity or Remediation Obligation ID), and the effective
Grade, Withdrawal, Exemption, and Revocation evidence IDs present at evaluation; absence of
an Attempt SHALL be an explicit evidence value. A D/F component SHALL contain the distinct
Course and effective Grade IDs. A Department Head or Council dismissal SHALL suppress that
exact fingerprint from opening another Case and from producing Termination Pending.
Standing SHALL fall through to its next applicable value. A later Attempt outcome,
Withdrawal, decision/Revocation, or new Remediation Obligation SHALL produce materially
changed evidence and MAY open a new Case; unchanged non-completion SHALL remain suppressed.

A post-checkpoint Exemption Revocation SHALL suppress only its restored Preparatory
requirement from termination evidence until the Remediation Window closes. Other active
termination evidence remains actionable. If the Student holds a valid Incomplete in the
restored Course at that deadline, the normal Incomplete deferral applies.
An `Awaiting Offering` Remediation Obligation has no deadline and SHALL NOT become
termination evidence until an eligible Offering with a lawful add opportunity is scheduled.

A final Council termination SHALL atomically set the Identity-owned Student Lifecycle
Status to `Terminated` while retaining the User's Student Role. In the same transaction,
AcademicStanding SHALL invoke Enrollment's idempotent Public request-closeout command with
the Student ID and Termination Case ID. Enrollment SHALL append a system-attributed
`Rejected` transition to every non-terminal Registration Request and manual Exemption,
Transfer, or Waiver request, preserve all evidence, batch lines, reviews, and capacity
decisions, and notify the Student and current reviewers. Failure of any Case, lifecycle, or
request change SHALL roll back all of them. Existing Enrollments and effective credit
decisions SHALL remain unchanged. A Terminated Student may
authenticate and read their historical Grades, Credit-Hour Summary, requests, decisions,
and Transcript, but SHALL NOT create or change Course Registrations or manual Exemption,
Transfer, or Waiver requests. Reinstatement requires a future explicit workflow and SHALL
NOT follow automatically from remediation.

---

### 4.9 Transcript & Academic Records Module

#### 4.9.1 FR-TRN-001: Transcript Generation

> **Localhost scope amendment ([ADR-0045](adr/0045-phase-6-print-and-verification-scope.md)):**
> Phase 6 delivers bilingual on-screen and browser-printable Transcripts, immutable official
> issuances with seal/watermark and QR verification. Server-generated PDF and cryptographic
> digital signatures below are deferred production requirements. Issuer attribution is
> required but is not a digital signature. See [ADR-0044](adr/0044-official-transcripts-preserve-issued-snapshots.md)
> for immutable issuance semantics.

> **Verification disclosure ([ADR-0046](adr/0046-qr-links-disclose-full-issued-transcripts.md)):**
> An official issuance's unguessable QR link publicly displays the full issued Transcript
> without login. It grants access only to that snapshot, not current records, other
> issuances, evidence attachments, review notes, or Audit Logs.

> Verification links have no automatic expiry. They retain access to the issued snapshot
> until explicit Student Affairs revocation; graduation, termination, account changes,
> and newer issuances do not invalidate them.

> **Revocation ([ADR-0047](adr/0047-official-transcript-revocation-preserves-history.md)):**
> Student Affairs may explicitly revoke an issuance with a mandatory reason and recorded
> actor/timestamp while retaining its immutable snapshot internally. Public verification
> then reveals only its reference and Revoked status. New issuance alone does not revoke
> earlier copies.

**Acceptance Criteria:**

1. The system SHALL generate transcripts in two formats:
   - **Unofficial Transcript** – viewable by Student, Academic Advisor, and Student Affairs
   - **Official Transcript** – issued only by Student Affairs, with university seal/watermark and digital signature
2. The system SHALL export transcripts in PDF format with university branding, anti-tampering watermark, digital signature, and QR code for verification.

#### 4.9.2 FR-TRN-002: Credit Hour Summary

**Official Transcript Request workflow (Phase 6 scope extension):**

- Records owns requests, Transcript fee configuration, payment verification, refund
  obligations, delivery tracking, and official issuances. Phase 6 introduces no general
  Finance module. Academic and identity facts remain with their existing owners and are
  consumed through Public contracts (ADR-0049).
- Phase 6 includes Student requests, approval queues, fees, and delivery tracking.
- Student Affairs is the sole request approval authority and records a reason when
  approving or rejecting a request. Advisors, Department Heads, and Deans have no request
  approval stage.
- Request approval authorizes processing, not certification of an academic snapshot.
  Official issuance remains a separate action after the fee requirement is satisfied;
  the academic snapshot is captured at issuance, not request submission or approval.
- Every official issuance must belong to an approved Student request with verified
  payment. Student Affairs has no direct-issuance bypass.
- Each request produces exactly one issuance. Another issuance with a new reference
  requires a new request; viewing or printing an existing issuance does not create one.
- Submission fixes the delivery method and captured fee. Neither may be edited afterward;
  changing delivery method requires cancellation and a new request under the one-open
  request limit. Payment evidence may be submitted or corrected only at the approved
  workflow steps. Rejected requests remain terminal and cannot be resubmitted.
- Student Affairs must preview the Transcript before issuing it. If academic or identity
  content changes before issuance, refuse the stale preview and require review of updated
  content. The committed issuance must match the reviewed preview, including both
  language versions and summaries; consistency must cover all contributing Public
  owner-provided facts and cannot rely only on request state.
- A Student may have at most one open Official Transcript Request. It remains open through
  review, payment, issuance, and delivery, or until rejection or cancellation. A cancelled
  request with an outstanding refund remains open until that refund is completed.
- Delivery completion, rejection, cancellation without a refund obligation, or completed
  cancellation refund releases the limit. Enforce the limit transactionally so concurrent
  submissions cannot create multiple open requests.
- Request lifecycle:

| Status | Allowed progression |
|--------|---------------------|
| Submitted | Student Affairs approves to Awaiting Payment or rejects to Rejected |
| Awaiting Payment | Student submits evidence to Payment Under Review |
| Payment Under Review | Student Affairs verifies to Ready for Issuance or returns to Payment Correction Required |
| Payment Correction Required | Student resubmits to Payment Under Review |
| Ready for Issuance | Student Affairs issues to Completed (digital) or Ready for Collection |
| Ready for Collection | Student Affairs records handover to Completed |
| Cancellation Pending | Student Affairs denies and restores the previous stage, or approves to Refund Pending / Cancelled |
| Refund Pending | Student Affairs records the full refund to Cancelled |
| Completed / Rejected / Cancelled | Terminal request outcomes |

Cancellation Pending is available before issuance and pauses processing. Approved
cancellation enters Refund Pending if a verified payment is owed, otherwise Cancelled.
Preserve attributed, timestamped transition history and notify the Student of decisions
and required actions. Issuance validity and revocation remain separate from request status;
revoking an issued copy does not reopen its completed request.

Audit fee changes and every request, payment, cancellation, refund, issuance, revocation,
and collection action through Identity's audit writer. Notify the owning Student in-app
when a decision, correction, issuance, revocation, refund, or collection event affects
them. Student Affairs processes work from queues; no email or SMS is added. Public QR
reads are read-only and do not notify the Student on every scan. Request transition
history remains distinct from the security Audit Log.

Terminated Students may request official Transcripts and complete payment, cancellation,
refund, and delivery steps. These historical document services do not change academic
facts, restore enrollment rights, or reactivate the Student. Final termination leaves
pending Transcript Requests intact; Enrollment's academic-request closeout does not
apply to Records-owned Transcript Requests.

**Transcript payment handling (Phase 6):**

- Only the request-owning Student and Student Affairs may view payment references,
  receipts, refund evidence, and request processing history. Preserve the existing
  administrator override. Advisors' Transcript and issuance-history access does not
  include requests or financial processing, and public QR pages expose none of it.
- The Student submits a payment reference and receipt for the Official Transcript Request.
- Student Affairs manually verifies payment and records the amount, verifier, and
  verification timestamp. Submitting evidence alone does not satisfy the fee requirement
  or permit issuance.
- Online payment-gateway integration is explicitly deferred for the localhost build.
- Student Affairs manages one standard Transcript fee in EGP. Each request captures the
  applicable fee at submission, and later price changes affect only new requests.
- Student Affairs must configure a positive EGP fee and bilingual payment instructions
  before official requests open. Until configured, Transcript viewing remains available
  but official request submission is blocked. Each submitted request preserves its fee
  and both language versions of the payment instructions; later edits do not change
  existing requests. Instructions are shown to the Student only after approval.
- Payment verification requires one payment in EGP for exactly the request's captured
  fee. Return mismatched evidence for correction. Phase 6 does not support installments,
  combining receipts, or overpayment balances; a cancellation refund returns that exact
  verified fee.
- Payment instructions become available after request approval. Fee waivers are outside
  Phase 6.
- Student Affairs may return payment evidence for correction with a mandatory reason.
  The Transcript Request remains approved, but issuance is blocked until payment is
  verified. The Student may submit corrected evidence; earlier submissions and verification
  decisions remain in history. Returning evidence does not reject the Transcript Request.

**Transcript cancellation and refunds (Phase 6):**

- Before official issuance, the Student may request cancellation, including after payment
  verification. Student Affairs approves or rejects cancellation with a mandatory reason.
- Approved cancellation stops issuance and creates a refund obligation for the verified
  payment. No verified payment means no verified-payment refund obligation.
- Student Affairs records the manual refund's amount, reference, receipt, and completion
  time. Refund processing is in Phase 6; online payment integration remains deferred.
- After issuance, Transcript Revocation is separate and does not automatically trigger
  a refund. This pre-issuance cancellation workflow does not apply to issued copies.
- A pending cancellation blocks issuance and payment verification until Student Affairs
  decides it. Rejection resumes processing; approval permanently stops issuance.
- Cancellation submission, issuance, and payment verification serialize on the request
  and revalidate its state. If issuance commits first, cancellation is refused because an
  official copy already exists; if cancellation submission commits first, issuance and
  payment verification are blocked. Stale concurrent actions cannot both commit.
- Approved cancellation requires one full manual refund of the verified payment, without
  deductions or partial refunds. Completion requires the refund reference and receipt,
  amount, staff attribution, and completion time.
- A refund obligation may be completed only once; repeated completion submissions cannot
  record a second refund. This prevents duplicate recording in PRS, not duplicate
  transfers made outside the system.

**Transcript delivery (Phase 6):**

- The Student chooses digital access or collection from Student Affairs when submitting
  an Official Transcript Request. Postal delivery and courier integration are outside
  Phase 6.
- Digital delivery completes when the issued copy becomes available in PRS, with an
  in-app notification. Completion does not require proof that the Student opened it.
- Collection progresses from Ready for Collection to Collected. Student Affairs records
  the handover time and staff member.
- Delivery completion is distinct from request approval, payment verification, and
  official issuance; it does not change the immutable issued academic content.

**Authenticated Transcript access (Phase 6):**

- Students may view their own current unofficial Transcript and their own official
  issuance history, including revoked snapshots.
- Advisors may view current advisees' unofficial Transcripts and official issuance
  history, including revoked snapshots. Scope follows live Advisor assignment; former
  Advisors lose access on reassignment.
- Student Affairs may view all Students' unofficial Transcripts and official issuance
  history, including revoked snapshots, and may issue or explicitly revoke official copies.
- Other Roles receive no additional Transcript access in Phase 6. Preserve the existing
  administrator override. The public per-issuance QR access is governed separately by
  ADR-0046/0047 and does not grant authenticated browse access to other records.
- Revoked snapshots are visibly marked Revoked for authorized internal viewers. Historical
  read access remains available for Terminated Students under ADR-0040.
- No new Role or Permission is introduced: authenticated reads use the existing Transcript
  viewing permission and these scopes; issuance and revocation require the existing
  official-issuance permission. Deferred PDF export does not broaden read scope.

**Transcript content (Phase 6):**

- Each official issuance supports English/LTR and Arabic/RTL viewing and printing from
  the same frozen snapshot. Preserve both language versions of names and academic labels
  at issuance so later identity or Catalog edits cannot change an old copy. Language
  switching requires no new request, issuance, or payment.
- Header fields are Student name, University ID, Identification Type and its National ID
  or passport number, Faculty, Department, Program, degree level, admission term, and
  Student Lifecycle Status. The National ID/passport number is included in the full public
  QR view of a valid issuance, an explicitly approved disclosure.
- Transcript personal identity comes from Identity's admission-time Student identity
  snapshot. Phase 6 must preserve Identification Type explicitly in the admission
  handoff alongside the existing identifier; do not infer National ID versus Passport
  from number format. Recover existing Students' types from their accepted Applications
  through owner Public contracts. If required identity data cannot be recovered, block
  official issuance with a clear correction message.
- Academic rows show Course code/title, credit hours, classification, Grade or status,
  and an indicator when an Attempt no longer contributes to CGPA. Summaries include
  Semester GPA, CGPA, and the full per-category Credit-Hour Summary.
- Official certification shows issuance reference, date, issuer, seal/watermark, and QR.
  Contact details, payment information, review notes, and termination-case evidence do
  not appear in the Transcript or its public verification view.
- Official and unofficial Transcripts include all approved Enrollments grouped by Semester,
  including unfinished Courses labelled **In Progress**. Pending Registration Requests are
  not Enrollments and do not appear.
- Only finalized Grades are displayed, using Grading's authoritative current Grade at
  composition time; Draft or under-review marks never appear. All retake Attempts remain
  visible even when an earlier Attempt no longer contributes to CGPA.
- Audit, Withdrawal, Incomplete, and Fail Withdrawal display AU/W/I/FW as applicable under
  the established Course Status and Grade rules (ADR-0023).
- Effective Exemptions, Transfers, and Waivers appear in a separate credit-decisions
  section rather than as synthetic Enrollments. Their accounting and summaries remain
  authoritative owner-provided results.
- Credit-decision rows show target Course, recognized/released CH, decision type, and
  approval date. Transfers also show source institution, source Course, original Grade,
  and normalized E-JUST Grade; Waivers show the prior E-JUST Course and Grade; Exemptions
  show the qualification-based reason. Only effective decisions appear, without evidence
  attachments or reviewer notes. These fields appear in the full public QR view too.
- Official issuance may occur during an ongoing Semester and uses these same content
  rules; its content is frozen at issuance under ADR-0044.

| Category | Adjusted Required CH | Satisfied CH | Remaining CH | Status |
|----------|:--------------------:|:------------:|:------------:|:------:|
| Preparatory Courses | 6 minus Exempted CH | X | Y | Status indicator |
| Program Core Courses | 6 | X | Y | Status indicator |
| Research Elective | 9 | X | Y | Status indicator |
| **Total** | **21 (adjusted)** | **X** | **Y** | **Overall status** |

---

### 4.10 Notification & Communication Module

#### 4.10.1 FR-NTF-001: Notification System

**Supported Channels:**

- **In-App Notifications** – Bell icon with unread count
- **Email Notifications** – Automated emails for critical events
- **SMS Notifications** – Optional, for critical alerts

---

### 4.11 Calendar & Scheduling Module

#### 4.11.1 FR-CAL-001: Academic Calendar Management

**Configurable Periods:**

- Semester Start/End Dates
- Registration Period
- Add/Drop Period
- Late Add Period
- Withdrawal Deadline
- Grade Submission Deadline
- Final Exam Period
- Holiday/Break Periods

---

### 4.12 Reporting & Analytics Module

#### 4.12.1 FR-RPT-001: System Reports

| Report | Description | Accessible By |
|--------|-------------|---------------|
| Enrollment Summary | Students per program, faculty, degree level | SA, Dean, Dept. Head, SysAdmin |
| Application Pipeline | Applications by status, conversion rates | SA, Dean |
| Course Registration Summary | Enrollment per course, section capacity | SA, Advisor, Dept. Head |
| GPA Distribution | GPA/CGPA distribution by program | SA, Dean, Dept. Head |
| Academic Standing Report | Students by standing | SA, Dean, Dept. Head, Advisor |
| Graduation Readiness | Students approaching or academically ready for graduation; Warning alone does not exclude a Student whose curriculum is satisfied and CGPA is at least 3.00, while Termination Pending temporarily excludes them until dismissal or Trigger Cleared | SA, Advisor, Dept. Head |
| Termination Report | Students recommended/approved for termination | SA, Dean, Council |
| Audit Log Report | System activity logs, security events | SysAdmin, Super Admin |

---

## 5. Non-Functional Requirements

### 5.1 Performance Requirements

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-PRF-001 | Page load time | ≤ 2 seconds for 95th percentile |
| NFR-PRF-002 | API response time | ≤ 500ms for 95th percentile |
| NFR-PRF-003 | Concurrent users | Minimum 5,000 concurrent users |
| NFR-PRF-004 | Database query response | ≤ 200ms for standard queries |
| NFR-PRF-005 | File upload processing | ≤ 5 seconds for files up to 50MB |
| NFR-PRF-006 | Transcript generation (PDF) | ≤ 3 seconds per transcript |
| NFR-PRF-007 | GPA/CGPA recalculation | ≤ 1 second per student |
| NFR-PRF-008 | Report generation | ≤ 30 seconds for complex reports |
| NFR-PRF-009 | Notification delivery | ≤ 30s in-app; ≤ 5 min email |
| NFR-PRF-010 | Search functionality | ≤ 1 second for search results |

### 5.2 Availability & Reliability

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-AVL-001 | System uptime | 99.9% availability |
| NFR-AVL-002 | Planned maintenance | Saturday 2:00 AM – 6:00 AM |
| NFR-AVL-003 | Recovery Time Objective (RTO) | ≤ 1 hour |
| NFR-AVL-004 | Recovery Point Objective (RPO) | ≤ 15 minutes |
| NFR-AVL-005 | Automated failover | Within 60 seconds |
| NFR-AVL-006 | Database replication | Real-time synchronous |
| NFR-AVL-007 | Backup frequency | Full daily, incremental every 4 hours |
| NFR-AVL-008 | Backup retention | 90 days daily, 7 days incremental |

### 5.3 Security Requirements

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-SEC-001 | OWASP Top 10 | Full compliance |
| NFR-SEC-002 | Penetration testing | Annual third-party |
| NFR-SEC-003 | Vulnerability scanning | Automated weekly |
| NFR-SEC-004 | Code security | SAST in CI/CD pipeline |
| NFR-SEC-005 | Audit trail retention | Minimum 7 years |
| NFR-SEC-006 | API security | OAuth 2.0 / JWT |

### 5.4 Usability Requirements

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-USB-001 | Responsive design | Desktop, tablet, mobile |
| NFR-USB-002 | Browser support | Latest 2 versions of major browsers |
| NFR-USB-003 | Accessibility | WCAG 2.1 AA compliance |
| NFR-USB-004 | Language support | Arabic (RTL) and English (LTR) |
| NFR-USB-005 | Language switching | Runtime without page reload |
| NFR-USB-006 | Form auto-save | Every 30 seconds |

---

## 6. Integration Requirements

### 6.1 External System Integrations

| Integration | Direction | Protocol | Purpose |
|-------------|-----------|----------|---------|
| University LDAP/AD | Inbound | LDAP/SAML 2.0 | SSO Authentication |
| Email Service | Outbound | SMTP/REST API | Email notifications |
| SMS Gateway | Outbound | REST API | SMS notifications |
| Document Storage | Bidirectional | REST API | Encrypted document storage |
| Financial System | Bidirectional | REST API | Financial hold checks |
| Antivirus Scanner | Inbound | API/CLI | File upload scanning |
| PDF Generation | Internal | Library/API | Transcript generation |

### 6.2 API Specifications

1. All APIs SHALL follow RESTful conventions.
2. API versioning SHALL use URL path versioning (e.g., `/api/v1/`).
3. All APIs SHALL be documented using OpenAPI 3.0 (Swagger).
4. Authentication: OAuth 2.0 / JWT Bearer tokens.
5. Rate limiting: Configurable per endpoint (default: 100 requests/minute).
6. Response format: JSON with consistent error response structure.
7. Pagination: Cursor-based or offset-based with configurable page size.
8. All APIs SHALL support both Arabic and English via `Accept-Language` header.

---

## 7. Appendices

### 7.1 Appendix A: Grading Scale Reference

| Letter Grade | GPA Points | Percentage | Classification |
|:------------:|:----------:|:----------:|----------------|
| A+ | 4.00 | 97–100 | Exceptional |
| A | 4.00 | 93–96.99 | Excellent |
| B+ | 3.30 | 87–89.99 | Very Good |
| B | 3.00 | 83–86.99 | Good+ |
| C+ | 2.50 | 77–79.99 | Satisfactory+ (Min Pass) |
| C | 2.00 | 73–76.99 | Satisfactory |
| D+ | 1.30 | 67–69.99 | Below Average |
| D | 1.00 | 60–66.99 | Poor |
| F | 0.00 | Below 60 | Fail |

### 7.2 Appendix B: Credit Hour Tracking Categories

| Category | Definition |
|----------|-----------|
| Baseline Required CH | Credit hours required by the Student's bound Curriculum Version before Exemptions |
| Adjusted Required CH | `max(0, Baseline Required CH - Exempted CH)` |
| Attended CH | Raw credit hours of all attendance-counting Enrollments; repeated Attempts remain included |
| Earned CH | Raw credit hours from each Course's effective passing Attempt |
| Transferred CH | Raw credit hours from effective Transfer decisions (max 9) |
| Waived CH | Raw credit hours from effective Waiver decisions |
| Exempted CH | Raw credit hours from effective Exemptions; these reduce Required rather than count as Satisfied |
| Satisfied CH | `min(Adjusted Required CH, target-eligible Earned CH + Transferred CH + Waived CH)`; target-eligible Earned CH excludes a Course while an effective Exemption removes that same target |
| Remaining CH | `max(0, Adjusted Required CH - Satisfied CH)` |

Every figure SHALL be calculated for Preparatory, Program Core, and Research Elective,
plus a Total row that sums the category rows. Raw measures may exceed a requirement;
Satisfied CH SHALL allocate by Course target before applying the category cap. An effective
Exemption and raw Earned CH for its Course SHALL remain visible but SHALL NOT both advance
curriculum progress. Satisfied CH SHALL NOT exceed Adjusted Required CH and Remaining CH
SHALL NOT be negative.

### 7.3 Appendix C: System Configuration Parameters

| Parameter | Default | Configurable By | Description |
|-----------|---------|----------------|-------------|
| max_ch_per_semester | 12 | System Admin | Max CH per semester |
| min_ch_per_semester | 6 | System Admin | Min CH per semester |
| min_grade_per_course | 2.5 (C+) | System Admin | Minimum passing grade |
| min_cgpa | 3.0 (B) | System Admin | Min CGPA for graduation |
| max_retakes_per_course | 1 | System Admin | Max retake attempts |
| max_transfer_ch | 9 | System Admin | Max transferable CH |
| min_transfer_grade | B (3.0) | System Admin | Min grade for transfer |
| waiver_content_coverage | 80% | System Admin | Min coverage for waiver |
| session_timeout_minutes | 60 | System Admin | Session inactivity timeout |
| max_login_attempts | 5 | System Admin | Before account lockout |
| email_verification_hours | 24 | System Admin | Verification link validity |
| password_reset_minutes | 15 | System Admin | Reset link validity |
| incomplete_min_completion | 70 | System Admin | Min completion for I grade |
| audit_log_retention_years | 7 | System Admin | Audit log retention |

### 7.4 Appendix D: Academic Credit Counting Rules

**Enrollment Course Statuses:**

| Status | Transcript | CGPA | Req. CH | Attend. CH | Earned CH |
|--------|:----------:|:----:|:-------:|:----------:|:---------:|
| Regular | ✓ | ✓ | ✓ | ✓ | ✓ |
| Retake (same) | ✓ | ✓(latest) | ✓ | ✓ | ✓(latest) |
| Retake (diff. elective) | ✓ | ✓(both) | ✓ | ✓ | ✓(both) |
| Audit (AU) | ✓ | ✗ | ✗ | ✓ | ✗ |
| Withdrawal (W) | ✓ | ✗ | ✗ | ✗ | ✗ |
| CGPA Improvement | ✓ | ✓(latest) | ✓ | ✓ | ✓(latest) |
| FW | ✓ | ✗ | ✗ | ✗ | ✗ |
| Incomplete (I) | ✓ | ✗* | ✗* | ✓ | ✗* |

> \*until resolved

**Standalone course-targeted decisions:**

| Record | Transcript | CGPA | Accounting effect |
|--------|:----------:|:----:|-------------------|
| Transfer | ✓ | ✗ | Satisfies the mapped Curriculum Course; reported as Transferred CH |
| Waiver | ✓ | ✗ | Satisfies the targeted Curriculum Course; reported as Waived CH |
| Exemption | ✓ | ✗ | Reduces the targeted Preparatory requirement; reported as Exempted CH |

Only one approved Exemption, Transfer, or Waiver may apply to a Curriculum Course for a
Student. Transfer and Waiver may not duplicate credit already earned through an Enrollment.
A corrected Preparatory Exemption may coexist with an already-earned Enrollment because it
corrects requirement applicability, but target-aware Satisfied CH SHALL give no duplicate
progress.

---

*Document Version 1.0 – June 2025 – Confidential*
