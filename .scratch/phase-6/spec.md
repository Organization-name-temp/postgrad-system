# Spec — Phase 6: Records, Transcript Requests, Fees, Refunds, and Delivery

**Status:** ready-for-agent
**Feature slug:** phase-6
**Phase:** 6 of the Build Roadmap
**Scope:** SRS §4.9 and the approved Transcript service extensions; BR-63–65
**Primary decisions:** ADR-0044–0049, with ADR-0016's scoped verification exception

## Problem Statement

Students and academic staff can inspect Grades and academic progress, but cannot view a
composed Transcript or obtain an official, verifiable certification of the academic record.
Student Affairs cannot manage requests, confirm Transcript fees, issue certified copies,
track collection, or refund cancelled requests in PRS. Recipients cannot verify a copy
without asking the university, and later academic changes could make an uncertified live
view differ from what a recipient was given.

Students need both a current unofficial view and dated official copies that remain stable.
This includes Terminated Students seeking historical records. Student Affairs needs one
coherent service workflow, not separate screens that allow approval, payment, cancellation,
issuance, or refunds to contradict one another.

## Solution

Introduce Records as the owner of the Transcript service. Students and authorized staff
can view a bilingual current unofficial Transcript composed from authoritative academic
facts. Students request an official copy for digital access or collection from Student
Affairs. Student Affairs approves or rejects the request, verifies exact-fee payment
manually, reviews a current preview, and issues an immutable official Transcript.

Each issuance supports English and Arabic viewing and browser printing. Its QR link
publicly displays the full issued snapshot, including National ID/passport, without a
PRS account. The link does not expire automatically. Student Affairs may explicitly revoke
an issuance; its public page then shows only the reference and Revoked status, while
authorized internal users retain the original snapshot.

Before issuance, Students may request cancellation. Student Affairs decides it; approved
cancellation stops issuance and, when payment was verified, creates an obligation for one
full manual refund. Records tracks delivery and refunds separately from immutable academic
content, retains attributed history, and sends in-app notifications for relevant changes.

## User Stories

1. As a Student, I want a current unofficial Transcript, so that I can understand my complete academic record in one place.
2. As a Student, I want Enrollments grouped by Semester, so that I can follow my academic history chronologically.
3. As a Student, I want unfinished approved Courses labelled In Progress, so that pending work is not represented as completed.
4. As a Student, I want only finalized Grades displayed, so that provisional marks cannot be mistaken for certified results.
5. As a Student, I want every retake Attempt retained, so that my history remains truthful even when an earlier Attempt stops counting toward CGPA.
6. As a Student, I want AU/W/I/FW shown where applicable, so that non-letter outcomes are explained correctly.
7. As a Student, I want Course codes, bilingual titles, CH, and classifications, so that I can identify what I studied and how it was classified.
8. As a Student, I want indicators for Attempts no longer contributing to CGPA, so that historical results are distinguishable from effective results.
9. As a Student, I want Semester GPA and CGPA from the authoritative calculation, so that Transcript figures agree with my Grades screens.
10. As a Student, I want unavailable GPA shown as a dash, so that missing graded work is not represented as zero.
11. As a Student, I want the full per-category Credit-Hour Summary, so that requirements, raw credit facts, satisfied progress, and remaining CH are transparent.
12. As a Student, I want effective Exemptions, Transfers, and Waivers separate from Enrollments, so that prior-learning decisions are not mistaken for Courses taken in PRS.
13. As a Student, I want Transfer source institution, Course, original Grade, and normalized Grade displayed, so that recognized external study is understandable.
14. As a Student, I want Waivers to identify the prior E-JUST Course and Grade, so that the source of recognized study is clear.
15. As a Student, I want Exemptions to identify their qualification-based reason, so that released requirements are explained.
16. As a Student, I want my name, University ID, Identification Type and number, Program, degree, Faculty, Department, admission term, and lifecycle status on the Transcript, so that the record identifies me and my course of study.
17. As a Student, I want to view and print in EN/LTR and AR/RTL, so that the same record serves both languages.
18. As a Student, I want an official Transcript Request, so that I can obtain a university-certified copy.
19. As a Student, I want to choose digital access or collection when submitting, so that delivery matches my needs.
20. As a Student, I want to see the applicable EGP fee before submitting, so that I understand the cost.
21. As a Student, I want my request's fee preserved after submission, so that later fee changes do not change what I owe.
22. As a Student, I want official requests disabled until payment setup is complete, so that I am not charged against incomplete instructions.
23. As a Student, I want payment instructions after approval, so that I know when and where to pay.
24. As a Student, I want captured bilingual payment instructions to remain stable, so that later edits cannot change where I was told to pay.
25. As a Student, I want to submit a payment reference and receipt, so that Student Affairs can verify my payment.
26. As a Student, I want a reason when payment evidence needs correction, so that I can fix it without losing request approval.
27. As a Student, I want to resubmit corrected evidence with prior submissions preserved, so that the processing history remains visible.
28. As a Student, I want my request status and attributed timeline, so that I can see what happened and what action is needed.
29. As a Student, I want in-app notifications for decisions and required actions, so that I do not need to repeatedly inspect the queue.
30. As a Student, I want one open request enforced consistently, so that concurrent submissions do not create conflicting obligations.
31. As a Student, I want a new request allowed after completed delivery, rejection, or settled cancellation, so that I can request another issuance later.
32. As a Student, I want to request cancellation before issuance even after verified payment, so that I can stop a service I no longer need.
33. As a Student, I want pending cancellation to pause issuance and payment verification, so that my cancellation cannot be ignored while it is reviewed.
34. As a Student, I want a cancellation decision with a reason, so that I understand approval or rejection.
35. As a Student, I want an approved cancellation to refund my full verified payment, so that I recover the fee when no copy is issued.
36. As a Student, I want refund evidence and completion details, so that I can confirm the recorded return of payment.
37. As a Student, I want an outstanding refund tracked until completed, so that cancellation does not hide an unsettled obligation.
38. As a Student, I want digital delivery recorded when the issued copy is available, so that I can access it immediately.
39. As a Student, I want a Ready for Collection state and recorded handover, so that physical collection is tracked separately from issuance.
40. As a Student, I want my official issuance history including revoked copies, so that earlier certifications remain available to me.
41. As a Student, I want issued academic content to remain unchanged after Grade or identity edits, so that I can rely on what was certified.
42. As a Student, I want switching an issued copy's language without another request or fee, so that bilingual access is convenient.
43. As a Terminated Student, I want historical Transcript requests, payment, cancellation, refunds, and delivery, so that termination does not prevent obtaining my retained academic record.
44. As an Advisor, I want current advisees' unofficial Transcripts and issuance history, so that I can support their academic progress.
45. As an Advisor, I want access to follow live assignments, so that reassignment transfers record access appropriately.
46. As a Student Affairs staff member, I want scoped request queues with search, filters, sorting, and export, so that I can process work efficiently.
47. As a Student Affairs staff member, I want to configure a positive EGP fee and bilingual instructions, so that request setup is complete before opening the service.
48. As a Student Affairs staff member, I want to approve or reject requests with a reason, so that processing authority is explicit.
49. As a Student Affairs staff member, I want to verify one payment exactly matching the captured fee, so that incorrect payments do not authorize issuance.
50. As a Student Affairs staff member, I want returned evidence to preserve earlier submissions, so that corrections do not erase history.
51. As a Student Affairs staff member, I want payment verification attributed and timestamped, so that financial confirmation is accountable.
52. As a Student Affairs staff member, I want a bilingual preview before issuance, so that I can review what I am certifying.
53. As a Student Affairs staff member, I want stale previews refused, so that changed academic or identity facts require fresh review.
54. As a Student Affairs staff member, I want issuance blocked unless approval and verified payment are present, so that I cannot bypass the agreed workflow.
55. As a Student Affairs staff member, I want exactly one issuance per request, so that retries cannot create extra certified copies.
56. As a Student Affairs staff member, I want to decide cancellation and record one full refund, so that issuance and repayment cannot conflict.
57. As a Student Affairs staff member, I want to record collection time and staff attribution, so that handover is accountable.
58. As a Student Affairs staff member, I want to revoke an issuance with a reason, so that erroneous certifications can be explicitly withdrawn without erasing history.
59. As a Student Affairs staff member, I want unresolved identity data to block issuance clearly, so that official copies never guess the Student's Identification Type.
60. As a recipient, I want a public QR view of the complete issued Transcript, so that I can inspect the certified record without a PRS account.
61. As a recipient, I want the public view to show the dated snapshot rather than current Grades, so that it matches the copy I received.
62. As a recipient, I want a revoked link to show only its reference and Revoked status, so that I can identify a withdrawn certification without continued academic disclosure.
63. As a recipient, I want a valid QR link to remain available despite account changes or newer issuances, so that historical verification continues to work.
64. As a Student, I want financial evidence and request processing history excluded from Advisor and public QR access, so that sharing a Transcript does not share its service records.
65. As an administrator, I want the existing permission override preserved, so that administrative support follows established access rules.
66. As an auditor, I want fee and service actions recorded separately from request transitions, so that sensitive actions can be traced without confusing operational and security history.

## Implementation Decisions

### Ownership and existing seams

- Records owns Transcript composition, requests, fee configuration, payment evidence and
  verification, cancellation decisions, refund obligations/completion, delivery tracking,
  official snapshots, and revocations. No general Finance module or ledger is introduced.
- Enrollment remains owner of Enrollments and credit decisions. Reuse EnrollmentRecords
  and the ExemptionDecisions, TransferDecisions, and WaiverDecisions contracts.
- GradingRecords provides current finalized Grades, effective Attempts, CGPA, and Semester
  GPA. Reuse its authority for supersession and contribution indicators; do not recreate
  Grade or GPA rules in Records.
- CreditHourSummaries provides AcademicStanding's complete authoritative summary from the
  Student's bound Curriculum Version. Records preserves all Baseline Required, Adjusted
  Required, Attended, Earned, Transferred, Waived, Exempted, Satisfied, and Remaining figures
  by category and total. Do not recalculate target allocation, caps, or thresholds.
- Reuse Identity's StudentDirectory and Directory, Catalog's Course/Program/Curriculum
  contracts, Calendar's AcademicCalendar, Identity's AuditLogger, and Notifications' Notifier.
  Extend owner Public DTOs/contracts narrowly where identity, labels, or recovery facts
  are missing. No Internal imports, cross-module models, or cross-owner table reads.
- Explicit dependency edges permit Records to consume the needed owner Public contracts
  and domain-free Support primitives. Module discovery and feature composition follow
  existing conventions; no central provider registry or feature-to-feature imports.

### Identity and academic content

- Personal identity comes from Identity's admission-time Student snapshot. The existing
  admission handoff copies the identification number but omits Identification Type;
  preserve the type explicitly for new admissions and recover existing Students' types
  from accepted Applications through an Admissions Public contract. Recovery must be
  idempotent and must never infer type from number format. Provide a manually runnable
  recovery path consistent with the localhost build. Missing required identity blocks
  official issuance with an actionable message, rather than a guessed official field.
- Both Transcript types include approved Enrollments in every applicable Course Status,
  grouped by Semester; exclude pending Registration Requests. Retain every Attempt.
  Use recorded Enrollment CH/classification rather than mutable Catalog equivalents.
- Render the current finalized Grade when present; otherwise show AU/W/I/FW as applicable
  or In Progress for unfinished work. Draft and under-review marks never appear.
- GPA follows Grading's present effective-attempt semantics, including retroactive changes
  to earlier Semester GPA. Null is unavailable and displays as a dash, not zero.
- Header: Student name, University ID, Identification Type and National ID/passport number,
  Faculty, Department, Program, degree level, admission term, and lifecycle status.
- Rows: Course code/title, CH, classification, Grade/status, and non-contribution indicators.
  Summaries: Semester GPA, CGPA, and the complete authoritative CH Summary.
- Credit decisions are separate rows for effective decisions only: target Course, CH,
  type, approval date; Transfer source institution/Course, original and normalized Grades;
  Waiver prior E-JUST Course/Grade; Exemption qualification-based reason. Do not synthesize
  Enrollments or expose evidence attachments and reviewer notes.
- Contact details, payment information, request history, and termination-case evidence
  are excluded from Transcript content. Issuance during an ongoing Semester is permitted.

### Request state and fees

| Status | Legal progression |
|--------|-------------------|
| Submitted | Student Affairs approves to Awaiting Payment or rejects to Rejected, with a reason |
| Awaiting Payment | Student submits reference and receipt to Payment Under Review |
| Payment Under Review | Student Affairs verifies to Ready for Issuance or returns to Payment Correction Required with a reason |
| Payment Correction Required | Student resubmits evidence to Payment Under Review |
| Ready for Issuance | Student Affairs reviews and issues to Completed (digital) or Ready for Collection |
| Ready for Collection | Student Affairs records handover to Completed |
| Cancellation Pending | Student Affairs rejects with a reason and restores the prior stage, or approves with a reason to Refund Pending / Cancelled |
| Refund Pending | Student Affairs records the full manual refund to Cancelled |
| Completed, Rejected, Cancelled | Terminal request outcomes |

- Before issuance, cancellation submission enters Cancellation Pending and pauses processing.
  Approval goes to Refund Pending if a verified payment is owed, otherwise Cancelled.
- At most one open request per Student, including collection and outstanding refunds.
  Completed, Rejected, and Cancelled release the limit. Enforce transactionally.
- Submission fixes delivery method, captured fee, and bilingual payment instructions.
  No post-submission editing of these facts; changing delivery requires cancellation and
  a new request. Rejected requests cannot be resubmitted. Payment evidence changes only
  through the state machine, preserving earlier evidence and decisions.
- Student Affairs alone approves requests; no Advisor, Department Head, or Dean stage.
  Issuance requires an approved request and verified payment; there is no direct bypass.
- Student Affairs configures one positive EGP fee and bilingual payment instructions.
  Request submission is blocked until setup is complete; viewing remains available.
  Instructions become visible to the Student after approval. Later configuration edits
  affect new requests only. There are no fee waivers.
- Verify one EGP payment exactly matching the captured fee. No installments, combined
  receipts, or overpayment balances. Evidence alone never authorizes issuance.

### Issuance, consistency, and verification

- Each request creates at most one immutable issuance, with unique reference, issuer,
  timestamp, complete bilingual academic snapshot, and an unguessable verification token.
  Opening, printing, or switching language does not create another issuance or fee.
- Store both language versions of identity and academic display content needed to render
  the issued copy. Later owner edits cannot change old copies. Snapshot derived figures
  are historical document content, not authoritative live academic balances.
- Student Affairs must preview before issuance. Bind issuance to reviewed content and
  reject if any contributing academic or identity content has changed. Include both
  language versions and summaries. A request lock alone is insufficient because the
  academic owners can change independently. The implementation must guarantee a coherent
  committed snapshot through owner Public contracts and transactional consistency, with
  a meaningful concurrency test; it must not merely hash an inconsistent multi-read view.
- QR access is unauthenticated, per issuance, and unguessable; do not allow public lookup
  by sequential id or Student identifier. Full valid snapshots intentionally expose
  National ID/passport and the approved credit-decision details. Possession of the link
  grants that access, not browse access to current records or other issuances.
- Links do not expire automatically. Graduation, termination, account changes, or a newer
  issuance do not invalidate a prior link. Public reads have no workflow side effects
  or per-scan Student notification.
- Student Affairs may append a reasoned, attributed, timestamped revocation. Preserve
  the snapshot internally; the public response becomes reference and Revoked status only,
  omitting the reason and all academic content. New issuance does not auto-revoke old copies.
  Revocation is independent of request status and does not trigger an automatic refund.
- Render EN/LTR and AR/RTL on screen and for browser printing, with seal/watermark and
  QR on official copies. Issuer attribution is not a cryptographic digital signature;
  seal/watermark is presentation, not cryptographic anti-tampering protection.

### Cancellation, refunds, and delivery

- Serialize cancellation submission/decision, verification, issuance, and other request
  mutations and revalidate state. Issuance-first refuses cancellation; cancellation-first
  blocks issuance and payment verification. Denied cancellation restores the saved prior
  stage. Approved cancellation permanently prevents issuance.
- Refund the full verified payment in one manual refund, without partial amounts or
  deductions. Record exact amount, reference, receipt, staff attribution, and completion
  time. Complete each obligation once; retries cannot create another recorded refund.
  PRS does not initiate or prevent transfers made outside the application.
- Digital delivery completes when the issued copy is available in PRS, with a notification;
  opening acknowledgment is not required. Collection enters Ready for Collection and
  completes only when Student Affairs records handover time and staff member.
- Delivery and refund records do not mutate academic snapshots. Termination preserves
  pending Transcript services, which are not Enrollment-owned academic requests.

### Authorization, persistence, and API/UI contract

- Students read their own current Transcript and full issuance history, including visibly
  revoked copies. Advisors read the same for live advisees; reassignment transfers access.
  Student Affairs reads all and issues/revokes. Preserve administrator permission union.
- Existing Transcript viewing and official-issuance permissions remain authoritative.
  Use official-issuance authority for Student Affairs service/configuration mutations;
  Student-owned service mutations additionally require ownership and Student access.
  No new Role or Permission, and no expanded Dean access from the deferred PDF permission.
- Only the request-owning Student and Student Affairs read request processing history,
  payment references/receipts, and refund evidence, retaining administrator override.
  Advisors and public verification recipients do not receive those service records.
- Terminated Students may request and complete all historical Transcript services without
  changing academic facts or reactivating their lifecycle. Their historical reads persist.
- Records persistence covers fee setup, requests with captured pricing/instructions and
  delivery choice, versioned payment submissions/decisions, attributed transitions and
  cancellation decisions, refund obligations/completion, issuance snapshots/tokens,
  revocations, and collection evidence. Module-owned migrations enforce local integrity;
  cross-module references remain opaque IDs without foreign keys.
- Receipt uploads are private, validated using the established document-serving/upload
  conventions and exposed through authenticated fetch-as-blob. Retain evidence when
  corrected rather than overwriting files. Public verification never exposes private
  file identifiers or attachment URLs. No arbitrary external receipt-fetching service.
- API capabilities cover current Transcripts, scoped Student pickers, issuance history,
  fee setup, request submission/detail/queues, approval/rejection, payment evidence and
  verification/return, preview/issue, cancellation/decision, refund completion, collection,
  revocation, and public verification. Controllers/resources and documented responses
  generate the committed OpenAPI contract and SPA hooks/types; do not hand-edit generated code.
- Independently filterable queues/collections use Support pagination, deterministic
  sorting, readable-field filter/search allowlists, and audited XLSX export siblings.
  Aggregate request timelines and complete individual Transcripts remain aggregate reads.
  Export financial fields only for their authorized readers, and never export QR tokens.
  This collection export is distinct from deferred Transcript PDF export.
- Add a Records frontend feature; app composition handles routes/navigation. Reuse shared
  access gates, notifications, tables, status components, uploads, and design tokens.
  Student request/status/history screens and Student Affairs service queues expose only
  actions allowed by the backend state and access contract. Public verification works
  outside the authenticated shell and supports both languages and directions.
- Audit fee changes and all request/payment/cancellation/refund/issuance/revocation/
  collection actions through AuditLogger. Operational transitions remain separate.
  Audit payloads must not unnecessarily copy receipt bytes, identification numbers, or
  bearer tokens. Notifications carry typed data, not pre-rendered text, and target the
  owning Student for decisions, corrections, issuance, revocation, refunds, and collection.
  Deliver once for a committed transition; retries must not duplicate effects.

## Testing Decisions

- Prefer the existing highest integration seam: Pest HTTP feature tests driving Records
  routes against real owner Public implementations and PostgreSQL. Verify observable
  responses, durable histories, authorization, audits, and notifications; avoid tests
  coupled to private helpers, table layouts, or mirrored implementation logic.
- Extend Admissions/Identity feature coverage for new-admission Identification Type and
  idempotent recovery from accepted Applications. Prove missing recovery evidence blocks
  issuance and no number-format inference occurs.
- Use existing Grading sheet/correction and AcademicStanding credit-summary fixtures as
  composition prior art. Exercise finalized versus draft Grades, all Attempts, supersession,
  null GPA, Incomplete resolution/expiry, statuses, CH snapshots, effective decisions,
  Exemption-plus-earned-credit target accounting, and both language versions.
- Test every legal workflow transition and refusal from the wrong state, plus reasons,
  fee/instruction snapshots, positive setup gate, exact payment, evidence correction,
  fixed request fields, one-open-request limit, and delivery method outcomes.
- Test immutable issuances after corrections, Catalog/name/configuration changes and
  Incomplete resolution; new issuance does not replace or revoke the prior snapshot.
- Test stale preview refusal and real competing transactions: content-change versus
  issuance, issuance versus cancellation, verification versus cancellation, concurrent
  submissions, duplicate issuance, and duplicate refund completion. Use controlled
  database transaction interleavings where needed, not sleeps or mocked locks.
- Cover the access matrix with owner/non-owner Students, live/former Advisors, Student
  Affairs, other Roles, administrators, and Terminated Students. Verify mixed Roles retain
  the established union semantics without leaking financial records.
- Public verification tests require no login and assert full valid content including
  National ID/passport, no attachment/financial disclosure, no enumeration access,
  revoked reference/status-only response, and no automatic invalidation or per-scan
  workflow effects. Check response bodies, not just status codes, for disclosure limits.
- Use existing Admissions private-document tests as prior art for authenticated receipt
  serving, unauthorized downloads, correction history, and JSON authentication failures.
- Test queues/exports at the API seam for identical filters, scope, deterministic pagination,
  field allowlists, audit events, and identifiers preserved as spreadsheet strings.
- Focus Vitest/RTL coverage on interactive request/payment/cancellation/refund controls,
  state-driven actions, stale-preview recovery, and public revocation rendering. Reuse
  existing frontend patterns instead of testing generated client internals.
- One serial Playwright smoke covers Student submission, Student Affairs approval,
  payment correction/verification, preview/issuance, unauthenticated QR access, language
  switching, revocation, cancellation/full refund, and collection. Use separate requests
  sequentially so the one-open-request invariant remains real. Include EN/AR and RTL/LTR
  print-layout review, theme coverage, and relevant accessibility assertions.
- Implement behavior test-first. Run narrow tests during iteration, then backend and
  frontend affected gates including boundaries, static analysis, OpenAPI drift, and the
  changed full-stack flow. Report any gate not run with its concrete reason.

## Out of Scope

- Server-generated PDF/Browsershot integration and cryptographic digital signatures.
- Online payment gateways, automated transfers/refunds, general Finance accounting,
  installments, combined receipts, overpayment balances, fee waivers, partial refunds,
  and deductions.
- Postal delivery, couriers, delivery-provider integration, email, and SMS.
- Direct official issuance outside an approved paid Student request, multiple simultaneous
  open requests, multiple issuances from one request, and post-submission delivery edits.
- Automatic revocation after academic changes, automatic QR expiry, and automatic refunds
  triggered by post-issuance revocation. This spec defines no post-issuance refund workflow.
- Editing/deleting certified snapshots, public Student search, or public access to request
  histories, financial evidence, review notes, or termination-case evidence.
- Changes to grading arithmetic, credit-decision rules, formal graduation, academic
  reinstatement, and Phase 7 Reports.
- Production infrastructure/security-hardening beyond retained localhost requirements;
  no new queue, scheduler, cache, external identity service, or deployment stack.

## Further Notes

- Approved by the Phase 6 grill-with-docs session. ADR-0044 defines immutable issuance;
  ADR-0045 defines print/signing scope; ADR-0046/0047 define public disclosure and revocation;
  ADR-0048 preserves Terminated Student services; ADR-0049 establishes ownership.
- Full public disclosure deliberately includes National ID/passport. Do not substitute
  masking or login-only verification without a new user decision. This is a scoped
  exception to private-document access, not general publication of Student records.
- No actual fee, bank details, or credentials were supplied. Student Affairs runtime
  setup is required; do not invent institutional payment instructions. Demo setup may
  use clearly fictional, explicitly labelled learning data.
- Operational issuance is historically immutable even though Grade/CH values remain
  derived in their owners. Fee, refund, and delivery mutations never become academic facts.
- Testing seams were presented to the user for confirmation as required by to-spec;
  use the repository's existing HTTP/facade and full-stack seams unless redirected.
- This is a specification publication, not authorization to begin implementation or
  mark Phase 6 complete. Subsequent tickets should be vertical slices of this contract.
