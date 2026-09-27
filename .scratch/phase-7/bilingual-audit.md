# Phase 7 bilingual presentation audit

This record distinguishes source/runtime checks from browser coverage. No surface
is considered fully covered until its specified EN/AR × light/dark and responsive
states have executable or inspected evidence. Certified Transcript snapshots must
remain unchanged.

## Concrete defects

| ID | Surface | Evidence | Disposition |
| --- | --- | --- | --- |
| B05-01 | Waiver evidence download failure | EN/AR dictionary comparison found `enrollment.waivers.downloadError` absent in Arabic; the Arabic message was under the unrelated manual Exemption namespace. Existing page regression extended to Arabic failed, displaying English fallback. | Move the existing Arabic message to the Waiver namespace. Preserve the request/download behaviour; rerun the actual error rendering path in both languages. |

## Coverage so far

- Compared every frontend dictionary leaf: 1,866 entries in each locale before the
  fix, with one misplaced Arabic entry. This proves key-shape coverage only, not
  correctness of every translation or layout.
- Shared charts: serial `/style` Playwright/axe verification passes in all four
  combinations, including bar/line/pie and chart-as-table with exact values.
- Waiver evidence download error: focused page interaction exercises the failed
  authenticated download and rendered error in EN and AR.
- Backend message source audit: every English PHP dictionary has an Arabic
  counterpart with matching leaf keys. This does not prove message runtime paths
  or translation quality.

## Remaining coverage

- #04: authentication, shell/navigation, notifications, Dashboard, Catalog
  management/tree/curriculum/versions and Applicant/staff Admissions workflows.
- #05: registration/advising/on-behalf, offerings/schedules/attendance, capacity
  overrides, credit decisions, grading/corrections/Incompletes, Student Grades,
  Credit-Hour Summary, Standing and Termination review.
- #06: current/official Transcripts, service workflows, evidence, issuance/history/
  revocation and public valid/revoked/invalid verification, including EN/AR print.
- New Reports: role visibility, keyboard drill-through, criteria preservation,
  unavailable/error states, four presentation combinations and print layout.

All remaining items require the final acceptance pass. No broad audit claim is made
from dictionary parity alone.
