# Official transcripts preserve the academic record certified at issuance

**Status:** accepted

An unofficial Transcript reflects the current academic record. Each official Transcript
issuance preserves an immutable snapshot of what Student Affairs certified, with the
issuer and issuance timestamp. A later Grade correction or other academic change requires
a new issuance; it never rewrites an existing one.

Keeping issued documents live would make a previously certified record change when
reopened. Preserving the snapshot costs storage and requires a distinction between current
records and issued records, but retains the evidence of what was actually certified.

Records composes the snapshot from the owning modules' Public contracts, including
Grading's authoritative GPA/CGPA and AcademicStanding's Credit-Hour Summary. It does not
introduce editable academic balances or replace the current derived read models. Stored
derived figures are historical document content only, an explicit exception for issuance
to the current-read-model rules in ADR-0020 and ADR-0029.

Rendering/signing scope is resolved in ADR-0045, public verification in ADR-0046, and
revocation in ADR-0047. Authenticated access and the request workflow are recorded in
SRS §4.9 and the Phase 6 roadmap.
