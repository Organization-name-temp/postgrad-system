# Localhost learning build: production NFRs deferred, i18n retained

**Status:** accepted

The PRS is being built as a **localhost learning project** focused on domain and
workflow logic, not as a production deployment — despite the SRS being labelled
"Confidential – Production Release". We keep the documented *architecture* (two-tier
React SPA + Laravel REST API, modular-monolith seams, PostgreSQL) and the *domain*
(full RBAC, domain events, bilingual Arabic/English UI), but we defer the entire
production operations and security-hardening stack, stubbing external integrations
behind interfaces. This ADR exists so a future reader does not mistake the absent
infrastructure for unfinished work — it is deliberate scope.

## Considered options

- **Localhost learning build (chosen).** Maximises effort on the domain (credit-hour
  accounting, GPA/CGPA, academic standing, the admission/registration workflows), which
  is where the complexity and learning value are.
- **Production pilot (rejected for now).** Real deployment for a limited cohort; keeps
  security basics, defers scale/HA. More operational overhead than a learning build needs.
- **Full production per SRS (rejected for now).** K8s/HPA, on-prem, Vault KMS, ClamAV,
  LDAP/SAML, sync replica, 5,000 users, 99.9% uptime — an enormous undifferentiated ops
  investment before any domain code exists.

## Deferred / stubbed (the explicit "no"s)

- **Platform:** Kubernetes/HPA, on-prem hosting, nginx/TLS ingress → local dev servers.
- **Infra services:** Redis, MinIO, Reverb, ClamAV, Vault/KES, Horizon → not deployed.
  Files go to local disk; events run synchronously in-process; derived read models are
  computed on-demand rather than Redis-cached.
- **Auth hardening:** MFA/2FA, SSO/LDAP/SAML, account lockout, CAPTCHA, password policy,
  session listing/force-terminate, geolocation logging → minimal Sanctum session auth.
  [ADR-0051](0051-security-audit-remediation.md) adds narrow failed-login rate limits and
  an explicit first-party session precondition following the security audit; the other
  listed production controls remain deferred.
- **Notifications:** email + SMS → in-app notifications table with a polled bell only.
- **Scheduler:** cron/Laravel scheduler → artisan console commands run manually.
- **Resilience/scale:** synchronous Postgres replica, encrypted backups, and all
  performance/availability NFRs (5,000 users, 99.9%, RTO/RPO) are out of scope.

## Retained despite the above

- Two-tier React SPA + Laravel REST API ([ADR-0001](./0001-decoupled-spa-rest-api.md)).
- Modular-monolith facade + event seams, enforced by deptrac
  ([ADR-0002](./0002-modular-monolith-seams.md)).
- PostgreSQL ([ADR-0003](./0003-postgresql.md)) — kept so the GPA/standing math is honest.
- Full RBAC (R01–R10 + the SRS §3.2 permission matrix).
- In-process domain events — they drive the derived read models, not just notifications.
- **Bilingual Arabic (RTL) / English (LTR) from Phase 0** (NFR-USB-004/005) — the one
  production NFR retained, because retrofitting RTL late is disproportionately painful.

## Consequences

- Stubbed integrations sit **behind interfaces**, so a real driver (S3, SMTP, LDAP,
  ClamAV, a queue) can replace the local/fake one if the project is ever promoted toward
  a pilot or production — the callers do not change.
- Deferring caching removes a whole class of stale-read-model bugs during learning; the
  perf NFRs that justified the cache are themselves out of scope.
