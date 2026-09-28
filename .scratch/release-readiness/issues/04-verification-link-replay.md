# 04 — Resolve verification-link replay semantics

**What to build:** An Applicant’s email-verification link has an explicit contract for first use, re-clicks, expiry and resend, consistent with the localhost mail channel.

**Blocked by:** None — can start immediately.

**Status:** needs-info

- [ ] Compare FR-ADM-002.5 single-use wording with the current idempotent re-click controller and feature test; distinguish a replay response from a second state transition.
- [ ] Record whether idempotent acknowledgement satisfies the intended requirement or whether consumed-token enforcement is required; specify expiry and replaced/resend-link behaviour.
- [ ] If changing behaviour, keep owner workflow transitions once-only, preserve configured resend limits, provide EN/AR errors and cover replay/expiry/resend with HTTP and browser evidence.
- [ ] Update the accepted requirement/ADR or create a bounded implementation contract; real SMTP remains deferred by ADR-0005.
