# Records owns the Transcript service workflow and its fees

**Status:** accepted

Records owns Official Transcript Requests, Transcript fee configuration, payment
verification, refund obligations, delivery tracking, and official issuances. These
financial records serve only the Transcript workflow; Phase 6 does not introduce a
general Finance module or university-wide payment ledger.

Keeping the service workflow together makes approval, verified payment, cancellation,
refund, issuance, and delivery invariants local to one owner. A separate Finance module
was not chosen because the approved scope is manual, Transcript-specific payment and
refund recording rather than general financial operations. If broader financial services
are introduced later, their ownership will require a new explicit decision.

Records consumes academic and identity facts through their owners' Public contracts.
Enrollment retains Enrollments and credit decisions; Grading retains Grades and GPA/CGPA;
AcademicStanding retains Credit-Hour Summary; Identity retains Student identity and
lifecycle. Records does not recalculate those authoritative results. Issuance snapshots
are historical document content under ADR-0044, not new academic sources of truth.
