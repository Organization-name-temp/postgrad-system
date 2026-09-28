# Localhost release readiness

Audit date: 2026-09-28. Baseline: `44807f61223dbbebca8551c0f8844cb6fd24db78` on `main`.

The numbered phase implementations are merged, but a complete localhost release is
not yet accepted. Phase 4.6 has outstanding implementation tickets; manual lifecycle,
Arabic and print acceptance remains unrecorded. Production operation remains outside
[ADR-0005](adr/0005-localhost-learning-build.md).

## Verified publication and CI

Evidence was read from Git history and the GitHub API on the audit date. These are
observed results, not newly executed local package gates.

| Commit / run | Observed result |
| --- | --- |
| Phase 5 PR #9, `abd5f5d` | Merged in local first-parent history |
| [Phase 6 PR #10](https://github.com/MohamedYasser343/postgrad-system/pull/10), `b3a3ae7` | Merged; [main CI](https://github.com/MohamedYasser343/postgrad-system/actions/runs/36302985193) completed successfully |
| [Phase 7 PR #11](https://github.com/MohamedYasser343/postgrad-system/pull/11), `44807f6` | Merged at 2026-09-28 07:55:17 UTC |
| Phase 7 head `a1d01f9c6dd0643ae28f54911543601ed542db80` | [Push CI](https://github.com/MohamedYasser343/postgrad-system/actions/runs/36382219491) completed successfully: backend gate, frontend checks and full-stack Playwright; last job completed 06:08:45 UTC |
| Merge `44807f61223dbbebca8551c0f8844cb6fd24db78` | [Main CI](https://github.com/MohamedYasser343/postgrad-system/actions/runs/36394320641) observed in progress: backend and frontend successful, Playwright running. Recheck before release |

The workflow invokes `composer gate`; frontend client drift, lint, format, typecheck
and unit tests; and a separately seeded serial Playwright job. See
[the executable workflow](../.github/workflows/ci.yml). A green branch run is not
reported as a completed merge-commit run. Earlier tracker statements about an
unpublished CI fix are historical and superseded by this evidence.

## Requirement disposition

Use the [requirements audit](release-requirements-audit.md) for source/test traceability.
Implemented means the inspected path exists with relevant coverage; it does not certify
every sentence of an SRS family or constitute human acceptance. Missing means retained
localhost work. Deferred means an accepted scope decision explicitly leaves it out.
Unresolved means executable behaviour and written requirements need reconciliation.

## Canonical remaining Phase 4.6 tickets

Keep these existing issues as the implementation backlog; do not create duplicate issues
or infer completion from their planning merge. Blockers below describe the current
frontier after Phase 7, rather than rewriting their historical prerequisites.

| Ticket | Remaining delivery | Current blocking edge |
| --- | --- | --- |
| [07](../.scratch/phase-4.6/issues/07-remaining-lists-on-the-contract.md) | Calendar, Enrollment, Grading, Identity and Notifications collection contract/export coverage | Prerequisite 02/03/04 delivered to Phase 7's bounded extent; inspect the affected endpoint before starting. Split the large migration by owner into independently green slices |
| [09](../.scratch/phase-4.6/issues/09-gpa-distribution-report.md) | Grading-owned GPA/CGPA distribution and exact drill-through | 07's Grading oversight collection |
| [10](../.scratch/phase-4.6/issues/10-enrollment-owned-reports.md) | Enrollment Summary, Course Registration Summary and Attendance Analytics | Relevant 07 Enrollment collections. Deliver each Report as its own vertical slice |
| [11](../.scratch/phase-4.6/issues/11-audit-log-report.md) | Administrator Audit Log List/Report/export | Query/export foundation available; can start independently |
| [12](../.scratch/phase-4.6/issues/12-richer-dashboard-cards.md) | Scoped comparisons/trends on existing Cards | Relevant owner batch results and shared Chart available; verify each requested aggregate |
| [13](../.scratch/phase-4.6/issues/13-global-search-fan-out.md) | Permission-composed shell search | 07's searchable Student collection; Admissions/Catalog search already available |
| [14](../.scratch/phase-4.6/issues/14-column-chooser.md) | URL-persisted column choice with canonical export unchanged | Query/export foundation available; can start independently |
| [16](../.scratch/phase-4.6/issues/16-bulk-course-import.md) | Bilingual XLSX preview/apply round trip | Courses export and foundation available; can start independently |
| [17](../.scratch/phase-4.6/issues/17-headline-smoke-axe-phase-close-out.md) | Remaining Phase 4.6 integration and closeout | Outstanding 07/09/10/11/12/13/14/16; earlier prerequisites have evidence in Phase 7 |

Report ownership, scope, actor-readable filters and audited export remain governed by
[ADR-0025](adr/0025-support-layer-query-contract.md),
[ADR-0026](adr/0026-reports-owned-by-modules.md),
[ADR-0027](adr/0027-export-sibling-route-audited.md) and
[ADR-0028](adr/0028-aggregate-facades-sql-php-parity.md).

Additional discovered gaps and release tasks are one file per issue under
[release readiness](../.scratch/release-readiness/spec.md). Decision tickets are
`needs-info`; existing implementation tickets retain their canonical scope. A release
may carry an explicit accepted deferral, but no missing capability becomes deferred
merely because this audit found it.

## Acceptance record to complete

Record reviewer, date, commit, fixture, language/theme, observed result and evidence for
each row. Automated journeys are supporting evidence; the following human outcomes
remain pending. The baseline includes four implemented SRS Reports out of eight:
Pipeline, Standing, Readiness and Termination. Missing Reports must be delivered or
explicitly dispositioned before claiming full reporting scope.

| Journey | Required observation | Human result |
| --- | --- | --- |
| Applicant to Student | Draft/upload/verification, screening, Dean/interview decision, University ID and bound Curriculum Version | Pending |
| Registration and advising | Valid submission/approval, rejected invalid load or conflict, scoped on-behalf path, add/drop/withdrawal | Pending |
| Grading and academic progress | Entry/sign/review/correction, authoritative GPA/CGPA, retakes, Incomplete expiry, CH accounting | Pending |
| Credit decisions | Exemption/Transfer/Waiver evidence and review, revocation/remediation, accounting changes | Pending |
| Standing and Termination | Live scopes, actionable Case review, due process, historical reads after final Termination | Pending |
| Records | Request/payment/correction, preview/issuance, digital/collection, cancellation/full refund and revocation | Pending |
| Public verification | Frozen issued content, EN/AR, invalid link, revoked reference/status-only disclosure | Pending |
| Analytics | Counts match drill-through/export, actor scope cannot widen, filter bookmarks survive language change | Pending |
| Permissions | R01–R10, multi-role union and administrator set; forbidden actions denied by API | Pending |
| Bilingual presentation | EN/LTR and AR/RTL in light/dark; keyboard, long names, loading/error/empty states | Pending |
| Print | Real EN/AR A4 Transcript and Report previews: title/criteria/rows, QR legibility, watermark, page fit | Pending |

## Release completion checklist

- [x] Verify Phase 5/6/7 merge history and published Phase 7 correction.
- [x] Record successful branch CI and distinguish pending merge-commit CI.
- [x] Inventory retained gaps separately from accepted deferrals.
- [x] Create linked local issues for new gaps and acceptance/release work.
- [ ] Resolve or explicitly disposition remaining localhost requirements.
- [ ] Complete CI on the exact selected release commit.
- [ ] Follow the setup/runbook from a clean checkout, including a build and private upload/download.
- [ ] Record human lifecycle, EN/AR/RTL and print acceptance.
- [ ] Prepare release notes with known limitations and select/tag a release commit after acceptance.

No package gates were rerun for this Markdown-only audit; existing local evidence and
remote run results are attributed above. No real-data migration, release tag or
publication is part of this audit.
