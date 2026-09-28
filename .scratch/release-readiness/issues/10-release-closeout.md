# 10 — Close the accepted localhost release on an exact commit

**What to build:** The localhost release has an accepted scope, reproducible setup, traceable acceptance and successful checks on the exact selected revision.

**Blocked by:** 02–09 plus Phase 4.6 issue 17; explicitly accepted scope dispositions may replace implementation where documented.

**Status:** ready-for-agent

- [ ] Reconcile every missing/unresolved audit row to completed implementation or an explicitly accepted scope disposition; retain production deferrals under their ADRs.
- [ ] Confirm backend gate, frontend drift/static/unit checks and serial full-stack Playwright succeed on the exact selected commit; retain run URLs and conclusions.
- [ ] Complete the clean-checkout runbook verification and human acceptance record; reproduce and resolve release-blocking defects before closeout.
- [ ] Update ROADMAP/HANDOFF and requirement traceability to the accepted scope, with known limitations, demo instructions and exact verification evidence.
- [ ] Prepare release notes and a proposed release tag/revision for review. Publish/tag only within explicit authorization; this planning ticket does not itself perform publication.
- [ ] No missing Report, user-management capability or unresolved requirement is described as implemented solely because phases were merged.
