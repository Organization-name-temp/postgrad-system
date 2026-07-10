# Business Requirements Document (BRD)

## Postgraduate Registration System (PRS)

**Egypt-Japan University of Science and Technology (E-JUST)**

| | |
|---|---|
| **Document Version** | 1.0 |
| **Date** | June 2025 |
| **Source** | Extracted from SRS v1.0 |

---

## 1. Admission & Enrollment

| ID | Business Requirement |
|---|---|
| BR-01 | The system must manage the full lifecycle of postgraduate students from application through graduation |
| BR-02 | Applicants must complete a multi-step application: Personal Info → Email Verification → Document Upload → Program Selection |
| BR-03 | Duplicate applications must be prevented using email + national ID combination |
| BR-04 | Applications must go through a multi-stage approval: Student Affairs → Dean → Interview → Final Decision |
| BR-05 | Student Affairs must screen applications within 5 business days (configurable SLA) |
| BR-06 | Dean must review applications within 5 business days (configurable SLA) |
| BR-07 | Applicants can select 1 primary program and optionally 1 alternative program |
| BR-08 | Upon acceptance, the system must generate a University ID, assign an advisor, create academic record, and transition role from Applicant to Student |
| BR-09 | Admission decisions include: Accepted, Conditionally Accepted, Waitlisted, Rejected |
| BR-10 | Applicants must be able to track their application status at every stage |
| BR-11 | Interview scheduling must allow applicants to confirm or request one reschedule |
| BR-12 | Interview reminders must be sent 48 hours and 24 hours before the interview |

---

## 2. Academic Program Structure

| ID | Business Requirement |
|---|---|
| BR-13 | Total required credit hours for graduation: 21 CH |
| BR-14 | Preparatory Courses: 6 courses × 1 CH = 6 CH (4 university + 2 departmental) |
| BR-15 | Program Core Courses: 2 courses × 3 CH = 6 CH (must be from student's program) |
| BR-16 | Research Elective Courses: 3 courses × 3 CH = 9 CH (classified by level and research type) |
| BR-17 | Programs are organized hierarchically: University → Faculty → Department → Program |
| BR-18 | Deactivating a program must not affect currently enrolled students |

---

## 3. Course Registration

| ID | Business Requirement |
|---|---|
| BR-19 | Students must register between 6 (min) and 12 (max) credit hours per semester |
| BR-20 | Students approaching graduation may register below 6 CH if only remaining courses are left |
| BR-21 | All course registrations require Academic Advisor approval |
| BR-22 | Advisors and Student Affairs staff can register courses on behalf of students |
| BR-23 | Internal elective must be from the student's own program |
| BR-24 | External elective can be from any graduate program but requires advisor approval |
| BR-25 | Research course: PBL for MSc students, Seminar for PhD students |
| BR-26 | MSc courses are at level 500, PhD courses at level 600, Research at level 700 |

---

## 4. Add/Drop/Withdrawal Rules

| ID | Business Requirement |
|---|---|
| BR-27 | Week 1–2: Students can add or drop courses freely with no grade recorded |
| BR-28 | Week 3: Late add allowed with approval, no grade recorded |
| BR-29 | Week 4–X: Withdrawal allowed with "W" grade recorded, no refund |
| BR-30 | After withdrawal deadline: No withdrawal allowed, regular grade or FW assigned |

---

## 5. Exemption Rules

| ID | Business Requirement |
|---|---|
| BR-31 | IELTS ≥ 6.5 or TOEFL iBT ≥ 79 → automatic exemption from English Language course (1 CH) |
| BR-32 | PhD student who completed MSc at E-JUST → automatic exemption from all 6 preparatory courses (6 CH) |
| BR-33 | E-JUST BSc graduate → automatic exemption from Japanese Language, Japanese Culture, Key Skills (3 CH) |
| BR-34 | Maximum exempted credit hours from preparatory courses: 6 CH |
| BR-35 | Manual exemptions require approval workflow: Student → Advisor → Department Head |

---

## 6. Transfer & Waiver Rules

| ID | Business Requirement |
|---|---|
| BR-36 | Maximum 9 credit hours can be transferred |
| BR-37 | Minimum grade B required for course transfer |
| BR-38 | Transferred course must have an equivalent course in the program |
| BR-39 | Transfer requests must be submitted within 1 month of registration |
| BR-40 | Transfer approval: Student → Advisor → Dept. Council → Faculty Council |
| BR-41 | Course waivers apply to previously studied courses at level 400 or above |
| BR-42 | Minimum 80% course content coverage required for waiver |
| BR-43 | Waived courses do NOT affect CGPA |

---

## 7. Grading & GPA

| ID | Business Requirement |
|---|---|
| BR-44 | Grading scale: A+ (97–100) through F (below 60) with defined GPA points |
| BR-45 | Minimum passing grade: C+ (2.5 GPA points) |
| BR-46 | Minimum CGPA for graduation: 3.0 (B) |
| BR-47 | Instructors enter and sign/finalize grades; Department Head reviews |
| BR-48 | Incomplete (I) grade only allowed if student completed ≥70% of the course |
| BR-49 | Incomplete must be resolved before the 10th week of the next semester |
| BR-50 | Unresolved Incomplete grades auto-convert to FW |
| BR-51 | System must send reminders at 4 weeks, 2 weeks, 1 week, and 1 day before Incomplete deadline |

---

## 8. Re-Registration & Retake Rules

| ID | Business Requirement |
|---|---|
| BR-52 | Maximum 1 retake per course |
| BR-53 | On retake, latest grade replaces previous grade in CGPA |
| BR-54 | Both attempts must appear on the transcript |
| BR-55 | Elective courses below C+: student may register for a different elective; ALL grades count in CGPA |
| BR-56 | Preparatory and Core courses below C+: student MUST retake the same course; only latest grade counts |

---

## 9. Academic Standing & Termination

| ID | Business Requirement |
|---|---|
| BR-57 | Good Standing: CGPA ≥ 3.0 and all grades ≥ C+ |
| BR-58 | Academic Warning: Any course below C+ but fewer than 2 courses with D or below |
| BR-59 | Academic Probation: CGPA < 3.0 after completing coursework |
| BR-60 | Termination trigger 1: Failure to complete prep courses and English requirement within 2 consecutive main semesters |
| BR-61 | Termination trigger 2: Grade D (1.0) or less in 2 or more courses |
| BR-62 | Termination workflow: System flags → Student & Advisor notified → Dept. Council recommends → Academic Affairs Council decides → Student notified |

---

## 10. Transcript & Records

| ID | Business Requirement |
|---|---|
| BR-63 | Unofficial transcripts viewable by Student, Advisor, and Student Affairs |
| BR-64 | Official transcripts issued only by Student Affairs with university seal, watermark, digital signature, and QR code |
| BR-65 | Transcripts must track: Required CH, Attended CH, Earned CH, Remaining CH, Transferred CH, Waived CH, Exempted CH |

---

## 11. Document Requirements

| ID | Business Requirement |
|---|---|
| BR-66 | Required documents: Graduation Certificate, Academic Transcript, National ID/Passport, Personal Photo |
| BR-67 | Conditional documents: IELTS/TOEFL Certificate (if test selected), MSc Thesis (for PhD applicants) |
| BR-68 | Optional documents: CV/Resume, 2 Letters of Recommendation, up to 5 additional certificates |
| BR-69 | Maximum file sizes: 10 MB general documents, 5 MB for ID and photo, 50 MB for MSc Thesis |

---

## 12. Notifications & Communication

| ID | Business Requirement |
|---|---|
| BR-70 | Email notifications required for: application status changes, registration approvals, grade postings, academic warnings, interview scheduling |
| BR-71 | In-app notifications for all system events |
| BR-72 | Verification emails must be resendable (max 5 times per 24 hours) |

---

## 13. User Roles & Access

| ID | Business Requirement |
|---|---|
| BR-73 | 10 distinct roles: Applicant, Student, Academic Advisor, Instructor, Student Affairs, Department Head, Dean, Academic Affairs Council, System Admin, Super Admin |
| BR-74 | Users can be assigned one or more roles |
| BR-75 | Role assignments modifiable only by System Admin and Super Admin |
| BR-76 | Each role has defined permissions per the permission matrix |

---

## 14. Security & Compliance

| ID | Business Requirement |
|---|---|
| BR-77 | Account lockout after 5 failed login attempts for 30 minutes |
| BR-78 | Password policy: min 12 chars, 1 uppercase, 1 lowercase, 1 digit, 1 special char, no previous 5 passwords |
| BR-79 | Session timeout: 60 minutes of inactivity (configurable) |
| BR-80 | Audit trail retention: minimum 7 years |
| BR-81 | All uploaded files must be scanned for malware |
| BR-82 | Multi-language support: Arabic (RTL) and English (LTR) with runtime switching |

---

**Total: 82 Business Requirements** across 14 categories.

*Extracted from SRS v1.0 – June 2025*
