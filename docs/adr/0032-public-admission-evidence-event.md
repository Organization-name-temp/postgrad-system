# Admission acceptance publishes immutable exemption evidence

**Status:** accepted — amends [ADR-0013](0013-acceptance-handoff-student-anchor.md), whose
Phase 2 event was Internal and carried only notification identifiers

Phase 5 must apply automatic Exemptions after admission acceptance, but Enrollment may not
import `Admissions\Internal\Events\AdmissionAccepted` or read Admissions tables. Querying a
new Admissions facade would make accepted Applications a continuing runtime dependency;
calling Enrollment directly from the acceptance controller would reverse the intended
event-driven handoff and couple Admissions to a later module.

**Decision: Admissions publishes `AdmissionAccepted` as a `Public` event carrying an
immutable exemption-evidence DTO.** The payload contains the minted Student id, User id,
Application id, admitted Program id, previous degree, previous E-JUST Program id when
applicable, acceptance-time Program degree/Department semantics, and English-test type and
score. Enrollment handles the event idempotently and creates the approved automatic
Exemptions from that snapshot. It never queries the Application or imports Admissions
internals.

The event remains a post-commit side effect: admission acceptance and Student creation are
already durable when listeners run. Because a listener failure therefore cannot roll those
facts back, Enrollment also provides an idempotent reconciliation command for one accepted
Student. Retrying the event or running reconciliation converges on the same one Exemption
per Student and target Course; neither creates duplicates.

To recover even when no post-commit listener starts, the acceptance transaction also
freezes the same facts on the Identity-owned Student anchor as a typed
`Identity\Public\AdmissionEvidenceSnapshot`. Identity persists this value opaquely and
does not interpret or expose it in the `/me` representation. Enrollment may read it only
through `StudentDirectory` to recreate its missing receipt; after that, Enrollment remains
the sole owner of exemption decisions and reconciliation. Generic/seed-only Student mints
may omit the snapshot, but the reconciliation command fails observably rather than
silently succeeding when neither receipt nor anchor exists.

Enrollment records reconciliation completion on its local receipt. While an accepted
Student has an admission snapshot but no completed receipt, the exemption read and every
eligible-course/cart composition entry point fail with a retryable conflict. Reads never
trigger reconciliation; the standalone command remains the explicit repair path.

## Considered options

- **Public event with evidence snapshot (chosen).** Preserves one-way, event-driven module
  communication and lets Enrollment decide its own rules without a live Admissions read.
  The cost is a larger versioned event contract and an explicit recovery path for
  post-commit failure.
- **Admissions public evidence query (rejected).** Keeps the event small and makes the
  latest evidence queryable, but adds `Enrollment → Admissions`, couples exemption replay
  to Application retention, and turns an immutable admission fact into a temporal query.
- **Synchronous Admissions-to-Enrollment command (rejected).** Could make exemption creation
  part of acceptance's transaction, but makes Admissions depend on Enrollment, increases
  the failure surface of the acceptance workflow, and abandons the forward event hook
  reserved in ADR-0013.

## Consequences

- Cross-module listeners import `Admissions\Public\Events\AdmissionAccepted`; the old
  Internal event location is removed.
- The evidence DTO is a historical snapshot. Later edits elsewhere do not silently rewrite
  automatic Exemptions; corrections use the manual Exemption workflow or explicit
  reconciliation after correcting the accepted evidence.
- Event and reconciliation tests prove idempotency, including overlapping automatic rules
  that resolve to the same target Course.
- Failures are observable and recoverable without rolling back or re-accepting the Student.
