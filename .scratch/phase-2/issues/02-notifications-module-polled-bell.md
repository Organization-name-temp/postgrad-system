# 02 — Notifications module + polled bell

**What to build:** A cross-cutting `Notifications` module and the in-app bell that fills the slot Phase 0.5 reserved in the shell. Any module can enqueue a notification for a User through a Public contract; a User sees their unread count in a polled bell, opens it to read notifications **rendered in their own language**, and marks them read. No email, SMS, or websockets.

**Blocked by:** None — can start immediately (parallel with ticket 01; depends only on Identity).

**Status:** ready-for-agent

- [ ] New `Notifications` module (`Public`/`Internal`); `Notifications\Public` exposes a `Notifier::notify(userId, type, payload)` contract other modules call. `deptrac` layer added — `Notifications` reaches only `Identity\Public`; its `Internal` is sealed.
- [ ] `notifications` table: `user_id`, `type` (enum), `data` (JSON payload), `read_at` (nullable), `created_at`. **No pre-rendered text is stored** — content is a type + payload.
- [ ] Endpoints (all scoped to the authenticated user): list (paginated, newest-first), unread-count, mark-one-read, mark-all-read. A user cannot read or mutate another user's notifications.
- [ ] The app-shell **polled bell** shows the unread count and opens a panel listing notifications rendered **bilingually from the type via i18n**; marking read updates the count live.
- [ ] Pest feature tests: `notify` creates a row; list/unread-count/mark-read behave and are user-scoped; cross-user access → 403/404. Vitest: the bell renders the unread count and clears on mark-read.
- [ ] Scribe/Orval regenerated, drift-clean; all gates green.
