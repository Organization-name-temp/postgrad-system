# 06 — Deliver the Waiver workflow

**What to build:** Let a Student use qualifying prior E-JUST study to waive a Curriculum Course through Advisor, Department Head, and Academic Affairs Council review without allowing external study to bypass Transfer policy.

**Blocked by:** 04 — Deliver the manual Exemption workflow.

**Status:** ready-for-agent

- [ ] Waiver reuses the established Draft, submit, return, resubmit, reject, history, notification, and live-reviewer behavior.
- [ ] A request identifies a target Curriculum Course and prior E-JUST Course result, including official legacy transcript evidence when the prior record is absent from PRS.
- [ ] External study is rejected from the Waiver workflow and directed to Transfer rules.
- [ ] The prior E-JUST Course must be level 400 or above and completed at C+ or higher.
- [ ] The Advisor recommends first; the scoped Department Head records level/equivalency review and verified content coverage; Council makes the final decision.
- [ ] Coverage below 80% is rejected, and the recorded percentage and verification remain part of immutable review history.
- [ ] Final approval atomically creates an immutable effective Waiver decision using the target Curriculum Course hours.
- [ ] Waiver never affects CGPA and never creates a synthetic Enrollment or Grade.
- [ ] Student and reviewer API/UI surfaces enforce Role and scope and work in English/Arabic and LTR/RTL.
- [ ] Boundary, workflow, authorization, legacy-evidence, OpenAPI/client, export, and component tests pass.
