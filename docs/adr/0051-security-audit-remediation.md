# Security audit remediation within the localhost build

**Status:** accepted

The 2026-09-28 security audit found alternate Admissions paths that bypassed Faculty scope, stale workflow writes, unsupported stateless requests that committed before failing session setup, and vulnerable generated-client tooling. The user authorized remediation directly on main. These fixes retain the localhost scope of ADR-0005, with the following narrow amendment to its deferred authentication hardening.

## Decisions

- Screening queues, Application detail, timeline and private document reads share Admissions' ApplicationAccess policy. Owners retain self timeline/file reads. Student Affairs and System/Super Administrators may review university-wide; a Dean may review only Applications whose primary Program belongs to an assigned Faculty. Missing assignments or Program references deny Dean access. Effective access remains the union for multi-role Users; dedicated Dean-stage rules and administrative overrides remain governed by ADR-0015.
- Screening checks the current Application scope and requires Under SA Review while holding its PostgreSQL row lock. The general screening rejection cannot take over later Dean/interview decision stages. Every Application status transition locks the persisted row and compares the request's bound status before saving. Stale transitions return 409. Status and history commit atomically; two-step screening approval also retains one transaction. Denied or stale decisions do not emit notifications.
- Cookie-session login and public registration require Sanctum's first-party session pipeline before credential attempts or persistence. Unsupported stateless requests return a localized 400 rather than committing and then returning 500. Recognized first-party requests retain CSRF enforcement.
- Login failures have short-lived, configurable account and IP budgets, defaulting to 5 per normalized account and 60 per IP in 60 seconds. Exhaustion returns localized 429 plus Retry-After before password checks and failure Audit Log writes. Account and IP cache locks cover admission, credential verification and outcome; overlapping requests return 429 with a one-second retry rather than racing the counters. Successful authentication resets only the account budget; it never replenishes the IP budget. This is a local abuse backstop, not MFA, permanent lockout or the complete SRS production authentication stack.
- Upgrade advisory-affected dependencies within compatible major versions, regenerate the OpenAPI client, and retain an inert code-generation regression for the demonstrated Orval injection.

## Consequences

The fixed Role/Permission matrix is unchanged. Direct identifiers no longer bypass the screening queue's Faculty boundary. Competing Admissions decisions serialize rather than silently overwriting each other. SPA users see existing localized API errors through the current login UI; no new screen or workflow is introduced.

Email delivery, password-policy hardening, malware scanning, TLS deployment, MFA and the remaining production infrastructure remain ADR-0005 deferrals. Public full issued-Transcript capability links remain the deliberate exception under ADR-0046/0047. Audit baseline and remediation evidence live in `docs/security-audit/`.
