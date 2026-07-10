# 07 — Phase 0 demo capstone + headline Playwright smoke

**What to build:** The complete Phase 0 demo works as one flow and is protected by an end-to-end smoke — the roadmap's Phase 0 "Demo" made real and continuously verified. This is the integrate-and-verify capstone that ties authorization (05) and i18n (06) together in the authenticated shell.

**Blocked by:** 05 and 06.

**Status:** ready-for-agent

- [ ] The language switcher is present in the authenticated app shell alongside the dashboard.
- [ ] A Playwright smoke exercises the headline flow: log in as a seeded role → the guarded dashboard shows identity, roles, and effective permissions → toggle EN↔AR and assert the layout flips to RTL → log out and land back on login.
- [ ] The same smoke asserts that an unauthenticated visit to a guarded route redirects to login.
- [ ] The full gate (`composer gate` + `npm run gate`) is green, satisfying the Phase 0 Definition of Done.
