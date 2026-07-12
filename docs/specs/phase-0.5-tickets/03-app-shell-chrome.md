# 03 — App-shell chrome (app bar + role-aware side nav + user menu)

**What to build:** The app gains the persistent shell every later phase reuses. A top app bar
carries the brand lockup (shown on a light chip when it sits on a dark surface, so it stays
legible), the current page title, the language switcher, the theme toggle, and a user menu showing
who is signed in with a log-out action. A side navigation lists only the sections the signed-in
user's effective Permissions allow; it is permanent on large screens and collapses to a drawer on
smaller ones, with the active item clearly indicated and mirrored correctly in RTL.

**Blocked by:** 01 — Token-driven theme foundation.

**Status:** ready-for-agent

- [ ] Every authenticated screen renders inside the shell: app bar (brand, page title, language switcher, theme toggle, user menu) over a role-aware side nav.
- [ ] The brand logo stays legible on dark surfaces (light chip) and is not mirrored in RTL.
- [ ] Side-nav items are filtered by the user's effective Permissions from `GET /me` — a user without a section's permission never sees its item; the nav mirrors the API authority for display only.
- [ ] The side nav is permanent on large screens and a drawer on small ones; the active item is indicated with the brand accent on the leading side in both LTR and RTL.
- [ ] The user menu shows the signed-in identity and logs the user out.
- [ ] Adds `@mui/icons-material` (one icon set only). Gates green.
