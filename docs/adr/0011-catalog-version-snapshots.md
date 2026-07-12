# Catalog version history via append-only aggregate snapshots

**Status:** accepted

FR-PCM-001.3 and FR-PCM-003.4 require version history for all Program and Course changes.
This is **domain history** — what a Program or Course looked like at version N — and is
deliberately kept distinct from the **Audit Log** (Identity), which is a security trail of
*who did what* written only through `AuditLogger`; CONTEXT.md is explicit that the two are
separate things. A Catalog `catalog.manage` action may also write an audit row, but that
does not reconstruct past content.

We record history as an **append-only snapshot table owned by Catalog** (`catalog_versions`:
subject_type ∈ {program, course}, subject_id, `version_no`, `snapshot` JSONB, change_note,
changed_by, changed_at). Every create / edit / activate / deactivate writes one row with an
incrementing `version_no`; the snapshot is the **full aggregate** — for a Program, its
fields *and its Curriculum Entries and category requirements*. History is exposed read-only;
the UI diffs two snapshots.

## Considered options

- **Append-only aggregate snapshots (chosen).** Simple, hand-rolled (consistent with
  [ADR-0007](./0007-hand-rolled-psr4-modules.md)), test-first friendly, and it captures
  **Curriculum edits for free** because the whole aggregate is snapshotted — no need to
  version the join table separately. Cost: storage grows with edits (a non-issue at this
  scale) and a "diff" is computed from two JSON blobs rather than stored as deltas.
- **Temporal / SCD-2 rows in the main tables (rejected).** Point-in-time queries are
  powerful, but every read must filter `is_current`, every FK becomes version-aware, and
  `program_courses` would have to be versioned too — invasive and error-prone for a
  localhost learning build.
- **Off-the-shelf package — owen-it/laravel-auditing or spatie/activitylog (rejected).**
  Fast to adopt, but writes to a shared `audits`-style table (a module-ownership muddle),
  gives less control over the snapshot shape, does not capture Curriculum-link changes out
  of the box, and cuts against the hand-rolled ethos the project has followed so far.

## Consequences

- `catalog_versions` is **Catalog-owned** (Internal); it is not the Identity Audit Log and
  does not go through `AuditLogger`.
- Because snapshots are whole aggregates, adding a field to Program/Course needs no history
  migration — new fields simply appear in later snapshots; readers of old snapshots tolerate
  their absence.
- A future "revert to version N" feature is straightforward (re-apply a snapshot) but is
  **not** in Phase 1 scope — only recording and read-only viewing are.
- If a second module later needs versioning, the snapshot mechanism can be extracted to a
  shared concern then; nothing outside Catalog depends on it today.
