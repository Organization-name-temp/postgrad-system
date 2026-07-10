# PostgreSQL as the system of record

**Status:** accepted

We use PostgreSQL rather than MySQL/MariaDB. The PRS is as much an analytical system as a transactional one — eight report types, GPA/CGPA computation, academic-standing classification, and per-category credit-hour rollups. Postgres's window functions and CTEs make those computations tractable, JSONB fits the flexible audit-log and document-metadata payloads, native full-text search covers the catalog/tree search NFR without a separate service, and synchronous streaming replication satisfies the RPO ≤ 15 min / real-time replication requirements. Column-level PII encryption at rest is available via encrypted casts / pgcrypto with keys held in the KMS.

## Considered options

- **PostgreSQL (chosen).** Best fit for the report-heavy, integrity-critical workload.
- **MySQL / MariaDB (rejected).** More common in Laravel hosting and possibly more familiar, but weaker JSON and window-function ergonomics make the analytical half of the system harder to build and tune.

## Consequences

- Database choice is deep lock-in; the analytical SQL will use Postgres-specific features deliberately.
- Ops must run and monitor a primary + synchronous replica with automated failover to meet the availability NFRs.
