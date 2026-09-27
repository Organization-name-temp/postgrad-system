# Transcript certification shares owner-managed transactional read guards

**Status:** accepted

Official preview and issuance run in PostgreSQL transactions. Each contributing owner
exposes a Public `TranscriptReadGuard` that takes SHARE locks on its own source tables.
Records acquires Grading, Enrollment, Catalog, Calendar, then Identity guards before
locking the request and reading the owner Public contracts. AcademicStanding derives
its summary from those protected owners; Records does not recalculate it.

Table locks cover inserts and deletions as well as existing rows, preventing phantom
new Attempts or decisions. Once all guards are held, composition sees a coherent
committed state. A writer holding a conflicting lock finishes first; a later writer
waits until preview or certification commits. Deadlock victims retry the complete
transaction up to five times. These short, broad locks deliberately trade write
concurrency for correctness in the localhost learning build. A larger deployment
needs an owner-wide versioning protocol before replacing them with narrower locks.

Preview stores a random token digest, complete bilingual content digest, request,
reviewing actor and time separately from audit metadata. Certification repeats the
protected composition and accepts only an actor-bound review with identical content.
It inserts one immutable issuance under a unique request constraint and transitions
delivery in the same transaction. Certification and cancellation both lock the request;
whichever commits first determines the legal next action. Snapshot content and bearer
tokens are never copied into audit or queue exports.

This implements ADR-0044 and ADR-0049 without changing academic ownership or approved
public disclosure. Issuer attribution is not a cryptographic signature. Source edits
never update existing snapshots.
