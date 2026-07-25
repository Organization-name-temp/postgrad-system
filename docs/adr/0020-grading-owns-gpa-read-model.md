# Grading owns the GPA/CGPA read model, not Records

**Status:** accepted — supersedes [architecture.md](../architecture.md) §5's placement of the
GPA/CGPA read model in Records

[architecture.md](../architecture.md) §5 maps the "Grading & GPA" requirement onto
"Grading (+ **Records** for GPA/CGPA read model)", and §4.3 has Records composing the
Credit-Hour Summary. That placement does not survive contact with the build order: the
GPA/CGPA is needed in **Phase 4**, and Records is not born until **Phase 6**. Something has
to give, and three questions decide which: **who computes the average**, **which module
grows a new inbound seam**, and **who Phases 5 and 6 depend on**.

**The Grading module owns it.** `Grading` owns the Grade, and it owns the semester GPA and
the CGPA derived from Grades — exposed on `Grading\Public` as the one contract Phase 5's
Academic Standing and Phase 6's Records both read. Records, when it arrives, *composes a
transcript from* those numbers; it does not recompute them. The values are computed **on
demand and never stored** — no Redis in this build (ADR-0005), so the classic
stored-balance drift is structurally impossible rather than merely avoided.

**Why not Records, as written.** Records is a *document-issuance* context: its job is
official-versus-unofficial transcripts, seals, watermarks and QR verification. A GPA is
grade arithmetic, and it belongs beside the Grades it is arithmetic over. Honouring
architecture.md literally would mean birthing Records in Phase 4 containing one function,
adding a third module and its deptrac edges to an already large phase, and leaving a module
named "Records" that records nothing for two phases.

**Why not Enrollment.** Enrollment is the other plausible owner — it already holds the
credit-hours snapshot, the classification, and the `CourseStatusCounting` matrix the tally
reads, and Phase 5's Credit-Hour Summary is arguably its business. It was rejected because
it inverts the natural dependency: the Grade is the *new* fact, so the module that owns
Grades should depend on the module that owns Enrollments, not the reverse. Making
Enrollment compute the CGPA would force it to read `Grading\Public`, which is exactly the
cycle [ADR-0022](0022-grade-outcome-pushed-to-enrollment.md) exists to avoid.

**The tally straddles a seam, and that is fine.** A CGPA needs GPA points (Grading) times
the credit-hours snapshot, filtered by the `course_status` counting row (both Enrollment).
Grading therefore reads `Enrollment\Public`'s roster and enrollment surface — which is
precisely the read surface [ADR-0017](0017-offering-based-registration.md) deferred to
"Phase 4, built against grading". The seam this decision needs is the seam already
promised.

## Considered options

- **Grading owns the read model (chosen).** GPA lives beside the Grades; one new inbound
  seam, and it is one ADR-0017 already committed to. Phases 5 and 6 get a single contract.
  Costs an amendment to architecture.md §5.
- **Birth a thin Records module in Phase 4 (rejected).** Follows the recorded architecture
  literally, but creates a module two phases early holding one function, adds a third
  module and its deptrac edges to the phase, and leaves "Records" a misnomer until Phase 6.
- **Enrollment owns the read model (rejected).** Keeps all accounting in one module and
  reuses the counting matrix in place, but inverts the dependency direction and forces the
  `Enrollment → Grading` read this project has otherwise gone to some length to avoid.

## Consequences

- **architecture.md §5 must be amended**, and §4.3's "Records … Credit-Hour Summary
  composition" re-read as composition *from* Grading rather than ownership. A future reader
  finding GPA code in Grading and a contradictory architecture doc is exactly the confusion
  this ADR exists to prevent.
- Phase 5's Academic Standing and Phase 6's transcript both depend on `Grading\Public`;
  neither re-derives the average, so the rounding rule is applied in exactly one place.
- `Enrollment\Public` ships in Phase 4 with both a read surface and the command surface
  Grading needs — the module's `Public` layer goes from empty to load-bearing in one phase.
- architecture.md's cached-and-invalidated design (Redis, `GradeFinalized` invalidation)
  remains the documented later optimization; nothing here forecloses it, because callers
  already depend only on the facade.
