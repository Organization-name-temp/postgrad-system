# 01 — Resolve administrator Role assignment boundaries

**What to build:** An administrator can manage User Role assignments under a written contract that preserves the Student admission/lifecycle rules and scoped staff ownership.

**Blocked by:** None — investigation can start immediately.

**Status:** needs-info

- [ ] Confirm FR-SEC-002.4, BR-73–76 and ADR-0008 against existing Role assignment primitives and admission transitions; distinguish Role assignment from Dean/Head routing assignments.
- [ ] Resolve whether R01/R02 may be edited directly and what happens when a User lacks a Student anchor; assignment must not invent admission, graduation or reversal of Termination.
- [ ] Resolve R09/R10 delegation, self-removal/last-administrator handling, and stale Dean/Head/Advisor/Instructor assignment handling. Record accepted rules before implementation.
- [ ] Define audited actor/target/before/after facts, effective-permission refresh in active sessions and transactional concurrency behaviour; record the decision in the source-of-truth documents.
- [ ] Publish an unambiguous API/UI acceptance contract for issue 02 or record an explicitly accepted localhost deferral.
