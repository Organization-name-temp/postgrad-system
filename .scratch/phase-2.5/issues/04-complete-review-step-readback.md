# 04 — Complete the Review step read-back

**What to build:** Before finalizing, the applicant sees a complete read-back of everything captured — all personal-info fields grouped into sections, the selected program(s), and the list of uploaded documents (filename + type) — instead of the current three-field summary plus a bare document count. Per-section Edit jumps are ticket 08.

**Blocked by:** None — can start immediately. *(Workstream 1; may share a PR with 03. Root-cause + file references: [bugfixes.md](../bugfixes.md#b4).)*

**Status:** ready-for-agent

- [ ] The Review step renders every captured personal-info field, grouped (identity, contact & address, academic background, English proficiency).
- [ ] It lists the primary/alternative program(s) and each uploaded document's filename + type.
- [ ] The read-only status view of an already-finalized application is unaffected.
- [ ] Tests: the Review step shows the full field set + document list. Gates green.
