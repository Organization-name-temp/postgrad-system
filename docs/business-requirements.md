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
| BR-18 | Each Student is bound to the Program Curriculum version effective at admission; later Program edits or deactivation do not implicitly change that Student's degree requirements |

---

## 3. Course Registration

| ID | Business Requirement |
|---|---|
| BR-19 | Students register between 6 and 12 CH per Semester; in the enrollment Semester only, a 1–5 CH load is allowed when it includes every remaining non-exempt Preparatory requirement; no remaining Preparatory requirement means the normal floor applies, and Audit Courses do not qualify |
| BR-20 | A Student with more than 0 but fewer than 6 Remaining CH may register below 6 CH only when the proposed for-credit schedule satisfies every remaining Course target or category slot; this Approaching Graduation result is independent of CGPA and Termination Pending so their separate blockers do not suppress the completing schedule during Probation/due process; Audit Courses do not qualify |
| BR-21 | All Registration Requests begin Pending Advisor; ordinary requests then become Approved/Rejected, while a full Must-Retake Offering moves on Advisor recommendation to Awaiting Capacity Override; each full Offering requires a reasoned/audited decision from its own scoped Department Head, the request waits for all approvals, and any denial rejects the unchanged atomic batch; bidirectional idempotent capacity reconciliation retires active requirements when normal seats return, adds per-Offering requirements when requested Offerings newly fill, preserves existing decisions without duplication, and auto-approves the already-recommended request after whole-batch transactional revalidation when no exception remains, without reserving a seat or deleting history; replacement Preparatory Exemption approval terminally rejects an affected pending/awaiting request unchanged in the same transaction, preserves its capacity-review history, notifies the Student/current reviewers, and requires a new batch; final termination likewise rejects every non-terminal request with an attributed cause while preserving history and approved Enrollments; those actions are authorized by Role and scope rather than the ordinary registration-approval Permission, add no Permission/general-queue access, may exceed capacity only for the required retake, and displace no existing Enrollment |
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
| BR-32 | A PhD student whose previous E-JUST MSc Program belongs to the admitted PhD Program's Department → automatic exemption from all 6 preparatory courses (6 CH) |
| BR-33 | E-JUST BSc graduate → automatic exemption from the selected Program's Japanese Language, Japanese Culture, and Key Skills Preparatory Requirement Roles (3 CH) |
| BR-34 | Maximum exempted credit hours from preparatory courses: 6 CH |
| BR-35 | A manual Exemption supplies or corrects evidence for a configured Preparatory exemption rule and requires Student submission → Advisor recommendation → Department Head final decision; it may release a Preparatory requirement after a failed or successful Attempt because it proves the requirement did not apply, but the Attempt, Grade, raw Earned CH, and CGPA effect remain while that Course's Earned CH is excluded from Satisfied progress; manual Exemption/Transfer/Waiver requests support editable Drafts, reviewer return-for-revision, terminal rejection, and append-only transition history; a newly effective failed Grade automatically and idempotently rejects an in-review Transfer/Waiver request for that Course and notifies the Student/current reviewers; approved decisions are corrected only by an attributed Revocation from the same final authority, with a post-checkpoint Preparatory Exemption Revocation creating a distinct Remediation Obligation through the Grade-Submission Deadline of the first offered main Semester in which the Student can still add the Course at Revocation time (current only through late-add), or Awaiting Offering without termination eligibility until such an opportunity exists; approval of a replacement Preparatory Exemption atomically closes either state as Released By Exemption, rejects any non-terminal atomic Registration Request containing that Course without deleting review history, leaves an already-created in-progress Enrollment under ordinary drop/withdrawal/grading rules, removes matching non-final termination evidence through normal reconciliation, and never reverses final termination; final termination system-rejects every non-terminal Credit Decision Request without changing effective decisions; generic prior-course equivalence is a Waiver |

---

## 6. Transfer & Waiver Rules

| ID | Business Requirement |
|---|---|
| BR-36 | Effective Transfers recognize the mapped target Curriculum Course's CH and may total at most 9 CH per Student; source CH is evidence only, final approval revalidates the cap transactionally, and Revocation frees the recognized CH |
| BR-37 | Transfer retains the external grade exactly as printed; the Department Head records its normalized E-JUST Letter Grade, and only A+, A, B+, or B is eligible; neither value affects CGPA |
| BR-38 | A transferred course must have an equivalent Course in the Program; a new Transfer request cannot target a Course after the Student has an effective failed for-credit Attempt in it |
| BR-39 | Transfer requests must be submitted no later than the same timestamp one calendar month after admission acceptance, clamped to the destination month's last valid day; the deadline is inclusive and independent of Semester or Course Registration dates |
| BR-40 | Transfer approval: Student submission → Advisor recommendation → Department Head equivalency verification → Education & Academic Affairs Council final decision |
| BR-41 | Waivers apply only to prior E-JUST Courses at level 400 or above that were successfully completed at C+ or higher; an official E-JUST transcript may prove legacy study not held in PRS, while study outside E-JUST must use Transfer; a new Waiver request cannot target a Course after the Student has an effective failed for-credit Attempt in it |
| BR-42 | The Department Head must verify and record at least 80% content coverage for a Waiver |
| BR-43 | Waived courses do NOT affect CGPA; approval is Student submission → Advisor recommendation → Department Head level/coverage verification → Education & Academic Affairs Council final decision; the Council also holds explicit Revocation authority for approved Transfers and Waivers |

---

## 7. Grading & GPA

| ID | Business Requirement |
|---|---|
| BR-44 | Grading scale: A+ (97–100) through F (below 60) with defined GPA points |
| BR-45 | Minimum passing grade: C+ (2.5 GPA points) |
| BR-46 | Academic Graduation Readiness is an advisory derived result requiring every curriculum requirement satisfied, CGPA ≥ 3.0, and no actionable Termination Pending case; its owner returns `ready`, Remaining CH, nullable CGPA, Approaching Graduation, and typed RemainingRequirements/CgpaUnavailable/CgpaBelowMinimum/TerminationPending blockers that consumers do not reconstruct; Academic Warning alone does not block readiness or add a Good Standing requirement, while dismissal or Trigger Cleared immediately removes the temporary termination block; readiness never graduates the Student, changes lifecycle status, or replaces a future formal workflow and its non-course/administrative checks |
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
| BR-55 | After an elective Grade below C+, the Student may take a different elective to satisfy the curriculum slot, but the original Course's Grade remains effective for CGPA, Academic Warning, and D/F termination until superseded by a successful later Attempt in that same Course |
| BR-56 | Preparatory and Core Courses below C+ create Must-Retake Obligations, while a post-checkpoint Preparatory Exemption Revocation creates a distinct Remediation Obligation without implying an Attempt; both use the shared priority/timetable/full-capacity workflow while active; Remediation ends through Course satisfaction, the distinct Released By Exemption outcome, or checkpoint evaluation; when eligible Offerings exist they take priority over unrelated for-credit registration, and a full Offering remains available through the scoped Department Head's reasoned/audited capacity override; an unrelated scheduled Course does not make an obligation unavailable and must be removed/switched during an open modification window; all offered obligations must be selected when feasible, otherwise the system greedily walks Preparatory obligations by earliest termination/remediation checkpoint, origin time, then Course code, followed by Core obligations by oldest failed Attempt then Course code, including each only if an eligible Offering fits the remaining load/timetable and never skipping a feasible higher-priority Course to optimize count or CH; the Student chooses any eligible non-conflicting Offering for each required Course and the Advisor may change only that Offering; after modification closes no Enrollment is auto-dropped and an unselected obligation carries forward; other registration may proceed when none is offered; Audit neither satisfies nor clears an obligation; only the latest Grade counts |

---

## 9. Academic Standing & Termination

| ID | Business Requirement |
|---|---|
| BR-57 | Academic Standing is exhaustive: Not Yet Assessed when no finalized CGPA-bearing Grade exists; Good Standing when CGPA ≥ 3.0 and every effective Grade is ≥ C+ |
| BR-58 | Academic Warning: CGPA ≥ 3.0 and any effective Grade is below C+; satisfying an elective slot with a different Course does not supersede the original Grade, but Warning alone does not block graduation readiness once every curriculum requirement is satisfied |
| BR-59 | Academic Probation: authoritative rounded CGPA < 3.0 after any CGPA-bearing coursework is finalized; Semester load is capped at the configured minimum CH, including Audit load, while existing below-minimum exceptions may still apply; current Standing remains derived, while append-only Standing Observations record changes for once-only Student/Advisor notifications on adverse transitions and recovery |
| BR-60 | Termination trigger 1: after the second consecutive main Semester's Grade-Submission Deadline, any Adjusted Preparatory requirement remains unsatisfied; a Preparatory requirement restored by a later Exemption Revocation receives through the Grade-Submission Deadline of the first offered main Semester in which its Course has an eligible Offering and the Student can still lawfully add it at Revocation time (current only through late-add) before becoming actionable; without a future opportunity it remains Awaiting Offering, affects Remaining CH/readiness, notifies Student/Advisor/scoped Department Head, and cannot trigger termination; local Offering changes plus Calendar's ID-only Public SemesterChanged and Catalog's ID-only Public CourseChanged events share immediate idempotent reconciliation, with a global/per-Student command for recovery; before Enrollment, institution-controlled loss of its qualifying opportunity selects another same-Semester Offering, the earliest later lawful Semester, or Awaiting Offering, preserving history and notifying the same actors, while full capacity/Student conflicts do not postpone it and an existing Enrollment proves use; an active assigned checkpoint follows extensions but never an earlier Calendar edit, with every edit recording the official and retained effective dates and renewing notification, while evaluation freezes the deadline used; an unresolved Incomplete at either checkpoint defers determination until resolution or expiry, and English is part of the Preparatory set rather than a second condition |
| BR-61 | Termination trigger 2: two or more distinct Courses have a current effective Grade of D or F; D+ does not qualify, one Course counts once, and only a successful later Attempt in the same Course clears it—passing a different elective does not |
| BR-62 | Termination Pending uses the Probation registration ceiling while the Student remains Active and temporarily blocks graduation readiness; each Student has at most one non-terminal Case aggregating every currently actionable trigger under a transactional uniqueness invariant; workflow: System flags → Student and Advisor notified → assigned Advisor assesses → scoped Department Head recommends or dismisses → Education & Academic Affairs Council terminates or dismisses → Student and Advisor notified; pending Advisor authority and queues follow live reassignment, while an already-recorded attributed assessment remains valid and the later-stage Case does not restart, and subsequent notifications target the current Advisor; idempotent event handlers and the manually run global/per-Student `academic-standing:reconcile` command share one service that evaluates elapsed checkpoints, opens or clears Cases, records Standing Observations, and sends once-only notifications without mutating during reads or converting Grading-owned Incompletes; a determinate remediation evaluation atomically invokes Enrollment's idempotent Public command using the obligation id and expected deadline, so a concurrent extension rolls back stale Case/Standing effects while a valid Incomplete leaves the obligation active; replacement Exemption approval and final Council termination serialize under one Identity-owned Student guard and revalidate after locking, so Exemption-first clears the trigger while termination-first blocks the academic mutation and both cannot commit stale outcomes; cleared triggers close an open Case, while a changed still-actionable fingerprint atomically closes it as Evidence Changed and opens a fresh Advisor-stage Case without transferring reviews or suppressing either fingerprint; dismissal or Trigger Cleared immediately restores the normal readiness test; dismissal suppresses only the exact deterministic trigger fingerprint, whose Preparatory components version the requirement with its ordinary/remediation checkpoint source and effective Grade/Withdrawal/decision/Revocation evidence (including no Attempt), so later authoritative evidence may open a new Case while unchanged non-completion remains suppressed; final termination atomically marks the Student Terminated without removing R02, system-rejects all non-terminal registration and credit-decision requests with history preserved, leaves approved Enrollments/effective decisions unchanged, preserves historical read access, blocks new academic mutations, and is never reversed automatically |

---

## 10. Transcript & Records

| ID | Business Requirement |
|---|---|
| BR-63 | Unofficial transcripts viewable by Student, Advisor, and Student Affairs |
| BR-64 | Official transcripts issued only by Student Affairs with university seal, watermark, digital signature, and QR code |
| BR-65 | Credit summaries and transcripts track per-category and total Baseline Required, Adjusted Required, Attended, Earned, Transferred, Waived, Exempted, Satisfied, and Remaining CH; raw measures remain visible, Satisfied allocates by Course target so Earned CH cannot also advance progress while an effective Exemption removes that target, Satisfied is then capped at Adjusted Required, and Remaining cannot be negative |

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
