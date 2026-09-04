# Termination requires Advisor assessment and Department Head recommendation

**Status:** accepted

The termination workflow says the Department Council recommends before the Academic
Affairs Council decides, while the fixed permission matrix grants `termination.recommend`
to both Advisor and Department Head. Allowing either actor to send a Case directly to the
Council makes departmental review inconsistent; removing the Advisor's authority leaves an
existing permission and the closest academic relationship unused.

**Decision: a flagged Termination Case requires two sequential pre-Council stages.** The
assigned Advisor records an assessment and recommendation. The scoped Department Head then
reviews that assessment and either recommends termination to the Education & Academic
Affairs Council or dismisses the Case. Only a Department Head recommendation sends the Case
to the Council, which makes the final terminate-or-dismiss decision.

Advisor scope is resolved from Identity at action time while the Case awaits assessment.
If the Student is reassigned, the former Advisor immediately loses authority and the new
Advisor receives it. An assessment already recorded before reassignment remains immutable,
attributed to its actor, and valid; the Case continues at the Department Head stage rather
than restarting. Subsequent notifications resolve the currently assigned Advisor.

Each Student may have at most one non-terminal Termination Case. Its fingerprint aggregates
the complete set of currently actionable Preparatory and D/F trigger components after
excluding dismissed evidence. Reconciliation creates it transactionally under a
persistence uniqueness invariant, so concurrent events or command runs cannot start
parallel workflows for the same Student.

Council finalization acquires Identity's transaction-scoped Student guard before any other
module lock, then revalidates the Case state and current authoritative trigger evidence. A
concurrent replacement Exemption that commits first therefore produces `Trigger Cleared`
instead of termination; if final termination commits first, Identity's lifecycle gate
prevents that Exemption approval. Commit order is authoritative, never the earlier screen
or recommendation snapshot.

Both actors hold `termination.recommend`, but authorization is transition-specific: the
Advisor can perform only the assessment transition, and the Department Head only the
departmental recommendation/dismissal transition. This follows the existing pattern in
which one broad Permission gates different workflow actions for different Roles.

Every non-terminal Case continues to represent exactly the fingerprint that opened it. If
reconciliation finds a different actionable fingerprint, it atomically appends terminal
`EvidenceChanged` to the old Case and opens a new Case at the Advisor-assessment stage. The
old recommendations remain immutable and carry no authority into the new evidence. If no
trigger remains, `TriggerCleared` applies instead. `EvidenceChanged` is not a dismissal and
suppresses neither the old nor new fingerprint; if an old fingerprint later becomes
current again, it may open a Case. The fresh Case sends the normal initial Student/Advisor
notification.

## Considered options

- **Advisor then Department Head (chosen).** Uses both existing authorities and makes the
  departmental recommendation consistent. It adds a stage omitted from the abbreviated
  SRS arrow.
- **Either may recommend directly (rejected).** Is simpler and mirrors the Permission matrix
  literally, but permits Cases to bypass Department Head review.
- **Department Head only (rejected).** Maps the named Department Council cleanly but removes
  any formal purpose for the Advisor's recommendation Permission.
- **Close and restart on evidence change (chosen).** Preserves immutable review context and
  repeats due process against the complete current evidence. Updating a Case in place loses
  what earlier actors reviewed; continuing unchanged lets stale recommendations reach the
  Council.
- **One aggregate Case per Student (chosen).** Gives reviewers and Student Lifecycle one
  coherent decision over all current evidence. Separate per-trigger Cases reduce review
  restarts but allow conflicting dismissals/final decisions and duplicate notifications.
- **Live Advisor before assessment; preserve completed assessment (chosen).** Aligns review
  authority with the Student's current academic relationship without discarding completed,
  attributable work. Restarting after every reassignment adds delay without changing the
  evidence; retaining the original Advisor throughout leaves authority with a former owner.

## Consequences

- The Case state machine distinguishes waiting for Advisor assessment, waiting for
  Department Head review, waiting for Council decision, and terminal `EvidenceChanged` in
  addition to the other terminal outcomes.
- A database-backed uniqueness invariant permits at most one non-terminal Case for a
  Student. Case creation, `EvidenceChanged` replacement, and terminal transitions preserve
  that invariant atomically and idempotently under concurrent reconciliation.
- Before every action and after any relevant academic change, the module recomputes the
  triggers. With none active, it appends a terminal `Trigger Cleared` transition, blocks
  further review actions, and notifies the Student and Advisor. A later trigger creates a
  new Case rather than reopening the old one.
- A changed but still-actionable fingerprint atomically closes the old Case as
  `EvidenceChanged` and opens a fresh Advisor-stage Case. Prior reviews remain history and
  no fingerprint is suppressed by this system transition.
- The same reconciliation runs from idempotent event handlers and the manually run
  `academic-standing:reconcile {student?}` command. The command is the localhost mechanism
  that notices elapsed ordinary/remediation checkpoints without mutating during reads; it
  opens a missing Case or clears an existing one and records Standing effects atomically.
- When the evidence comes from a determinate remediation checkpoint, that transaction also
  invokes Enrollment's idempotent, expected-deadline-guarded Public command. Case creation
  cannot commit while the registration obligation remains active, and a concurrent
  extension forces a reload rather than stale termination evidence.
- Advisor scope is the live assigned-advisee relationship while assessment is pending;
  reassignment transfers that action to the new Advisor and removes it from the old one's
  queue. A completed assessment remains valid and attributed, so later-stage Cases do not
  return to the Advisor. Department Head scope is the Student's bound Program Department;
  Council is unscoped within the Cases that reached it.
- Student and Advisor receive the initial flag notification; each subsequent dismissal,
  recommendation, or final decision emits the appropriate bilingual notification.
- Each Case freezes a canonical, deterministically ordered fingerprint of the evidence that
  opened it. Every Preparatory component contains the requirement id, checkpoint source
  (ordinary checkpoint identity or Remediation Obligation id), and the effective Grade,
  Withdrawal, Exemption, and Revocation evidence ids present at evaluation; no Attempt is an
  explicit evidence value. Each D/F component contains the distinct Course and effective
  Grade ids. Department Head or Council dismissal suppresses only that exact fingerprint.
  A later Attempt outcome, Withdrawal, decision/Revocation, or new Remediation Obligation
  changes it and may open a Case; unchanged non-completion cannot immediately reopen one.
- Dismissed evidence is not actionable for Standing, so the Student falls through to
  Probation, Warning, or Good. Dismissal does not permanently waive future trigger evidence.
- Fingerprint tests prove input ordering is irrelevant, repeated reconciliation is stable,
  no-Attempt evidence remains suppressed while unchanged, and each authoritative evidence
  version named above can create a new fingerprint.
- An actionable open Case temporarily blocks graduation readiness. Department Head or
  Council dismissal, or the automatic `Trigger Cleared` transition, removes that block
  immediately; Warning alone is not a graduation gate.
- A requirement restored by a post-checkpoint Preparatory Exemption Revocation is not
  actionable evidence until its ADR-0037 Remediation Window closes. Other active evidence
  may still open or continue a Case during that window.
- Final Council termination and replacement Exemption approval share Identity's per-Student
  transaction guard and fixed Identity-first lock order. No stale interleaving may commit
  both outcomes.
- After final revalidation, Council termination invokes Enrollment's idempotent Public
  request-closeout command and Identity's lifecycle command in the same transaction as the
  final Case decision. Partial termination cannot leave active request queues behind.
