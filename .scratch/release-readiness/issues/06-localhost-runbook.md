# 06 — Document and verify a reproducible localhost release

**What to build:** A fresh developer can install PRS, run the seeded demo and understand which manual commands advance or recover workflows.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] Create a root entry point and project-specific setup/demo guide, replacing or redirecting framework-template READMEs; use the executable PHP/Node/Compose configuration as the version authority.
- [ ] Cover first installation, PostgreSQL dev/test databases, key generation, migration/seeding, Laravel/Vite ports and proxy/session origins, seeded personas, private upload/download and build commands.
- [ ] Explain destructive reseeding separately from normal startup; demonstrate the instructions in an isolated disposable environment and record commands/results and any prerequisites.
- [ ] Document existing manual reminder/reconciliation/recovery commands and the state-changing Incomplete expiry command, their purpose and required ordering; do not introduce cron or workers.
- [ ] Provide a complete lifecycle demo and known limitations, linking accepted deferrals and audit gaps; record what has not been verified.
- [ ] Run a build and appropriate gates on the selected code revision, with serial Playwright and no competing reseed; record a concrete reason for any unavailable check.
