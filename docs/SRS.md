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
| Preparatory Courses | 6 | 1 | 6 | 4 University Requirements + 2 Departmental Requirements |
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
│   │   │   │   ├── DEP501 - Dept. Req. 1 (1 CH)
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
| PhD student who completed MSc at E-JUST | All 6 preparatory courses | 6 |
| E-JUST BSc graduate | PRE403, PRE404, Key Skills | 3 |

Maximum exempted credit hours from preparatory courses: **6 credit hours**.

**Credit Hour Limits per Semester:**

| Case | Min CH | Max CH | System Behavior |
|------|:------:|:------:|-----------------|
| Regular student | 6 | 12 | Block registration below 6 or above 12 CH |
| Student approaching graduation | Remaining | 12 | Allow below 6 CH if only remaining courses |
| First semester – exempted | < 6 | 12 | Allow below minimum in first semester only |

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
2. The registration request SHALL be sent to the assigned Academic Advisor for approval.
3. The Academic Advisor SHALL be able to approve, reject, or modify the registration.
4. Registration approval status SHALL be displayed as: Pending / Approved / Rejected.
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
| Transfer | ✓ | ✗ | ✓ | ✗ | ✗ | Max 9 CH, min grade B |
| Waive | ✓ | ✗ | ✓ | ✗ | ✗ | Level 400+, ≥80% coverage |
| FW | ✓ | ✗ | ✗ | ✗ | ✗ | After deadline |
| Incomplete (I) | ✓ | ✗* | ✗* | ✓ | ✗* | ≥70% completed |

> \*until resolved

#### 4.4.6 FR-REG-006: Re-Registration Rules

**Acceptance Criteria:**

1. A student SHALL be allowed to re-register for a course maximum once.
2. The latest grade SHALL replace the previous grade in CGPA calculation.
3. Both attempts SHALL appear on the transcript.
4. **Elective Courses:** If below C+ (2.5), student MAY register for a different elective; ALL grades count in CGPA.
5. **Preparatory and Core Courses:** If below C+ (2.5), student MUST retake the same course; only latest grade in CGPA.

---

### 4.5 Exemption Management Module

#### 4.5.1 FR-EXM-001: Course Exemption Request and Processing

**Automatic Exemptions:**

1. The system SHALL automatically apply exemptions upon enrollment without requiring a student request.
2. Maximum exempted credit hours from preparatory courses: 6 CH.

**Manual Exemptions:**

3. Students SHALL be able to submit exemption requests for MSc → PhD same track and previously studied courses.
4. Approval workflow: Student → Academic Advisor → Department Head/Council

---

### 4.6 Course Transfer & Waiver Module

#### 4.6.1 FR-TRW-001: Course Transfer

**Acceptance Criteria:**

1. Maximum 9 credit hours transferable.
2. Minimum grade B required.
3. Must have equivalent course in program.
4. Approval workflow: Student → Advisor → Dept. Council → Faculty Council.
5. Requests MUST be submitted within 1 month of registration.

#### 4.6.2 FR-TRW-002: Course Waiver

**Acceptance Criteria:**

1. Previously studied courses at level 400 or above may be waived.
2. Minimum 80% course content coverage required.
3. Waived courses do NOT affect CGPA.
4. Appear on transcript as "Waived".

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
| Good Standing | CGPA ≥ 3.0 and all grades ≥ C+ | No action required |
| Academic Warning | Any course < C+ but < 2 courses with D or below | Display warning, notify student and advisor |
| Academic Probation | CGPA < 3.0 after completing coursework | Restrict to minimum courses |
| Termination Pending | Meets termination criteria | Trigger termination workflow |

#### 4.8.2 FR-AST-002: Enrollment Termination

**Termination Criteria:**

| # | Criterion | Details |
|---|-----------|---------|
| 1 | Failure to complete prep courses | Fails to finish preparatory courses AND English requirement within 2 consecutive main semesters |
| 2 | Grade D in 2+ courses | Receives grade D (1.0) or less in two or more courses |

**Termination Workflow:**

System Flags → Student & Advisor Notified → Dept. Council Recommends → Academic Affairs Council Decides → Student Notified

---

### 4.9 Transcript & Academic Records Module

#### 4.9.1 FR-TRN-001: Transcript Generation

**Acceptance Criteria:**

1. The system SHALL generate transcripts in two formats:
   - **Unofficial Transcript** – viewable by Student, Academic Advisor, and Student Affairs
   - **Official Transcript** – issued only by Student Affairs, with university seal/watermark and digital signature
2. The system SHALL export transcripts in PDF format with university branding, anti-tampering watermark, digital signature, and QR code for verification.

#### 4.9.2 FR-TRN-002: Credit Hour Summary

| Category | Required CH | Completed CH | Remaining CH | Status |
|----------|:-----------:|:------------:|:------------:|:------:|
| Preparatory Courses | 6 (adjusted) | X | Y | Status indicator |
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
| Graduation Readiness | Students approaching graduation | SA, Advisor, Dept. Head |
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
| Required CH | Total credit hours needed for degree completion (default 21, adjusted for exemptions) |
| Attended CH | Total credit hours of courses the student has attended |
| Earned CH | Total credit hours of courses the student has passed |
| Remaining CH | Required CH – Earned CH – Transferred CH – Waived CH |
| Transferred CH | Credit hours counted from transferred courses (max 9) |
| Waived CH | Credit hours counted from waived courses |
| Exempted CH | Credit hours from exempted courses (reduce required total) |

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

### 7.4 Appendix D: Course Status Counting Rules

| Status | Transcript | CGPA | Req. CH | Attend. CH | Earned CH |
|--------|:----------:|:----:|:-------:|:----------:|:---------:|
| Regular | ✓ | ✓ | ✓ | ✓ | ✓ |
| Retake (same) | ✓ | ✓(latest) | ✓ | ✓ | ✓(latest) |
| Retake (diff. elective) | ✓ | ✓(both) | ✓ | ✓ | ✓(both) |
| Audit (AU) | ✓ | ✗ | ✗ | ✓ | ✗ |
| Withdrawal (W) | ✓ | ✗ | ✗ | ✗ | ✗ |
| CGPA Improvement | ✓ | ✓(latest) | ✓ | ✓ | ✓(latest) |
| Transfer | ✓ | ✗ | ✓ | ✗ | ✗(noted) |
| Waive | ✓ | ✗ | ✓ | ✗ | ✗(noted) |
| FW | ✓ | ✗ | ✗ | ✗ | ✗ |
| Incomplete (I) | ✓ | ✗* | ✗* | ✓ | ✗* |
| Exempted | ✓ | ✗ | ✗(reduces) | ✗ | ✗ |

> \*until resolved

---

*Document Version 1.0 – June 2025 – Confidential*
