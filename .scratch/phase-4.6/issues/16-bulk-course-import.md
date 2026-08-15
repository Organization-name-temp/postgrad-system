# 16 — Bulk Course import

**What to build:** Closes **SRS FR-PCM-003.5**, deferred since Phase 1 and affordable now that the
spreadsheet library is already paid for. A catalog administrator exports the Courses grid, edits the
file, uploads it, reads a preview of exactly what would change, and applies it — turning a hundred
form submissions into one reviewed act.

**The round trip is the design.** The Courses export from ticket 06 **is** the import template, so
the loop is export → edit → preview → apply and there is no separate template to generate, document
or keep in step with the schema.

**Two calls, and the split is the whole safety story.**

1. **Upload → dry-run preview.** Every row is validated and the response describes what *would*
   happen: rows to create, rows to update, and rows rejected with **per-row, per-column reasons**.
   **Nothing is written.** The user's feedback loop is a description of the system's view of their
   file, rather than repeated upload-and-reject against a file they cannot see the system's
   reading of.
2. **Confirm → apply.** The **whole file lands in one transaction, or none of it does.** A typo on
   row 400 never leaves the Catalog in a state matching neither the file nor what the administrator
   started with. The batch writes **a single `catalog_versions` snapshot** (ADR-0011), so the
   version history reads as one deliberate act rather than four hundred.

Details that will bite if missed:

- **The uploaded file is held on the private local disk under a ULID between the two calls**,
  reusing the ADR-0016 pattern, rather than persisting a parsed preview. The confirm call references
  the ULID.
- **Both English and Arabic names are required on every row.** The Catalog stores every name as a
  required pair, and bulk loading must not be the back door that creates Courses the Arabic
  interface renders blank.
- **The Lecture + Tutorial + Lab = Total invariant and the `DegreeLevel`/`CourseLevel` enums apply
  identically to imported rows.** Import is a different door into the same domain rules, never a
  second set of them.
- **Gated on `catalog.manage`**, the Permission that already governs Catalog writes. No new
  Permission — this is a bulk path to an authorized action, not a new one.
- **Import is only for Courses.** No other resource, and no import through the export route.

**Blocked by:** 03 — Export foundation; 06 — Admissions and Catalog lists on the contract.

**Status:** ready-for-agent

- [ ] An `.xlsx` upload returns a preview listing rows to create, rows to update, and rejected rows
      with per-row, per-column reasons
- [ ] The preview writes **nothing** — asserted by a test that uploads an entirely valid file and
      confirms the Catalog is unchanged
- [ ] A confirm call applies the whole file in one transaction; a file with one invalid row changes
      nothing at all
- [ ] An applied batch writes exactly **one** `catalog_versions` snapshot, not one per row
- [ ] The file is held on the private local disk under a ULID between the calls, per the ADR-0016
      pattern
- [ ] Rows missing either the English or the Arabic name are rejected with that reason
- [ ] The credit-hour invariant and the level enums are enforced identically to the single-Course
      form
- [ ] A file produced by the Courses export imports cleanly with no edits — the round trip is
      asserted by a test
- [ ] Gated on `catalog.manage`, returning 403 without it; no new Permission is added
- [ ] `openapi.yaml` and the Orval client regenerated and committed
- [ ] `composer gate` and `npm run gate` green
