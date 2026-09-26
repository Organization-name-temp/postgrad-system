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

Records owns the Transcript service workflow: requests, fee configuration, payment
verification, refund obligations, delivery tracking, and issuances. Phase 6 does not
introduce a general Finance module; academic and identity facts remain with their owners
and are consumed through Public contracts (ADR-0049).

| ID | Business Requirement |
|---|---|
| BR-63 | Unofficial transcripts viewable by Student, Advisor, and Student Affairs |
| BR-64 | Official transcripts issued only by Student Affairs as immutable snapshots with issuer and timestamp, university seal, watermark, and QR verification; the localhost Phase-6 scope requires bilingual on-screen/browser-print delivery and explicitly defers server-generated PDF and cryptographic digital signing (ADR-0044/0045); issuer attribution is not a digital signature |
| BR-65 | Credit summaries and transcripts track per-category and total Baseline Required, Adjusted Required, Attended, Earned, Transferred, Waived, Exempted, Satisfied, and Remaining CH; raw measures remain visible, Satisfied allocates by Course target so Earned CH cannot also advance progress while an effective Exemption removes that target, Satisfied is then capped at Adjusted Required, and Remaining cannot be negative |

Official Transcript QR verification publicly displays the full immutable issued Transcript
through an unguessable per-issuance link without login (ADR-0046). Possession of that link
grants access to the issuance, not the Student's current record or underlying private evidence.

Verification links do not automatically expire. Graduation, termination, account changes,
and newer issuances do not invalidate them; explicit Student Affairs revocation changes
their public response to reference and Revoked status only.

Student Affairs may explicitly revoke an official issuance with a mandatory reason and
recorded actor/timestamp; the snapshot remains internal, while its public QR page shows
only the reference and Revoked status. A new issuance does not automatically revoke
earlier copies (ADR-0047).

Both Transcript types show all approved Enrollments grouped by Semester, including
unfinished Courses labelled In Progress, finalized Grades only, every retake Attempt,
and applicable AU/W/I/FW indicators. Effective Exemptions, Transfers, and Waivers appear
separately. Official issuance is allowed during an ongoing Semester and freezes the
academic content at issuance.

Each official issuance supports EN/LTR and AR/RTL viewing and printing from the same
frozen snapshot, preserving both language versions of names and academic labels at
issuance. Later identity or Catalog edits cannot change old copies. Language switching
requires no new request or payment.

Transcript headers include Student name, University ID, Identification Type and National
ID/passport number, Faculty, Department, Program, degree level, admission term, and
lifecycle status. Academic rows show Course code/title, CH, classification, Grade/status,
and indicators for Attempts no longer counting toward CGPA. Include Semester GPA, CGPA,
and full per-category CH Summary; official copies also show issuance reference/date/issuer,
seal/watermark/QR. The National ID/passport number is explicitly included in the full public
QR view of a valid issuance. Contact details, payment information, review notes, and
termination-case evidence are excluded.

Transcript identity uses Identity's admission-time Student snapshot. Preserve
Identification Type explicitly during the admission handoff and recover existing Students'
types from accepted Applications through owner Public contracts, without guessing from
number format. Official issuance is blocked with a correction message if required
identity data cannot be recovered.

Transcript credit-decision rows include effective decisions only, showing target Course,
recognized/released CH, type, and approval date. Transfers additionally show source
institution/Course, original Grade, and normalized E-JUST Grade; Waivers show prior E-JUST
Course/Grade; Exemptions show qualification-based reason. These fields also appear in the
public QR view, without evidence attachments or reviewer notes.

Authenticated Transcript reads include unofficial content and official issuance history,
including visibly revoked snapshots: Students access their own, Advisors access live
advisees' (losing access on reassignment), and Student Affairs accesses all and may issue
or revoke. Other Roles gain no additional access beyond the existing administrator
override; public QR access remains separate. Terminated Students retain historical read
access. Existing Transcript-view and official-issuance permissions govern these actions,
without a new Permission; revocation requires official-issuance authority.

Phase 6 also includes Student requests for official Transcripts, approval queues, fees,
and delivery tracking. Student Affairs alone approves or rejects each request with a
reason; no Advisor, Department Head, or Dean approval is required. Approval authorizes
processing, while issuance is separate and occurs only after the fee requirement is
satisfied. The certified academic snapshot is captured at issuance.

Every official issuance belongs to an approved Student request with verified payment;
Student Affairs cannot issue directly outside that workflow. Each request produces exactly
one issuance; another issuance reference requires a new request. Reopening or printing
an existing issuance does not create a new one.

Student Affairs previews the Transcript before issuance. Changed academic or identity
content invalidates the preview and requires fresh review; the committed snapshot must
match the reviewed content in both languages and summaries across all contributing owners.

A Student may have at most one open Transcript Request, including requests awaiting
delivery or cancellation refunds. Rejection, completed delivery, cancellation without a
refund obligation, or completed cancellation refund permits a new request. Enforce the
limit transactionally across concurrent submissions.

Submission fixes a Transcript Request's delivery method and captured fee; changing
delivery requires cancellation and a new request. Payment evidence changes only through
approved submission/correction steps. A rejected request is terminal and cannot be
resubmitted.

Transcript Requests follow Submitted → Awaiting Payment → Payment Under Review (with
Payment Correction Required and resubmission when needed) → Ready for Issuance →
Completed for digital delivery, or Ready for Collection → Completed for handover.
Rejected is terminal. Before issuance, Cancellation Pending pauses processing; denial
restores the previous stage, while approval enters Refund Pending then Cancelled if a
verified payment is owed, otherwise Cancelled immediately. Retain attributed, timestamped
transition history and notify the Student of decisions and required actions. Issuance
revocation does not reopen the request.

Audit Transcript fee changes and all request, payment, cancellation, refund, issuance,
revocation, and collection actions. In-app notifications inform the Student of decisions,
corrections, issuance, revocation, refunds, and collection; Student Affairs works from
queues. No email/SMS or per-scan QR notification is introduced. Request transitions and
the security Audit Log remain distinct.

Terminated Students retain access to historical Transcript services, including official
requests, payment, cancellation, refund, and delivery. Termination leaves pending
Transcript Requests intact; these document services do not alter academic facts or
restore enrollment rights.

Transcript fees use manual payment verification: the Student submits a payment reference
and receipt; Student Affairs verifies payment and records the amount, verifier, and time.
Evidence upload alone does not satisfy the fee requirement. Online payment-gateway
integration is deferred for the localhost build.

Only the owning Student and Student Affairs may read Transcript Request processing
history, payment references/receipts, and refund evidence, with the existing administrator
override preserved. Advisor Transcript and issuance-history access does not extend to
requests or financial processing. Public QR pages disclose none of those records.

Student Affairs may return payment evidence with a mandatory correction reason without
rejecting the approved Transcript Request. Issuance remains blocked until verification;
the Student may resubmit corrected evidence, preserving earlier submissions and decisions.

Before issuance the Student may request cancellation even after payment verification;
Student Affairs approves or rejects it with a reason. Approved cancellation stops issuance
and creates a refund obligation for the verified payment. Student Affairs records the
manual refund amount, reference, receipt, and completion time. Refund processing is in
Phase 6. Post-issuance revocation remains separate and does not automatically trigger a
refund.

Pending Transcript cancellation blocks issuance and payment verification. Rejection
resumes processing; approval permanently stops issuance. Concurrent request mutations
serialize and revalidate: issuance committed first prevents cancellation, while
cancellation submitted first blocks issuance and payment verification.

Approved Transcript cancellation refunds the full verified payment in one manual refund,
without deductions or partial refunds. Completion records amount, reference, receipt,
staff attribution, and completion time. Each refund obligation can be completed only once;
repeated submissions cannot record a second refund in PRS.

Student Affairs manages one standard Transcript fee in EGP. Each request preserves the
applicable fee at submission; later fee changes affect only new requests. Payment
instructions become available after approval. Phase 6 does not include fee waivers.

Student Affairs configures a positive EGP fee and bilingual payment instructions before
official requests open. Before setup, Transcript viewing remains available but request
submission is blocked. Requests snapshot the fee and both language versions of instructions
at submission; later edits affect only new requests. Captured instructions are shown
after approval.

Transcript payment verification requires one EGP payment exactly matching the captured
request fee. Mismatched evidence is returned for correction. Phase 6 excludes installments,
combining receipts, and overpayment balances; full refunds return the exact verified fee.

At request submission, the Student chooses digital access or collection from Student
Affairs. Digital delivery completes when the issued copy becomes available in PRS and
triggers an in-app notification. Collection progresses from Ready for Collection to
Collected, with Student Affairs recording handover time and staff member. Postal delivery
and courier integration are outside Phase 6. Delivery does not alter the issued snapshot.

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
