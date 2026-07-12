# Technology Stack — Postgraduate Registration System (PRS)

**Egypt-Japan University of Science and Technology (E-JUST)**

| | |
|---|---|
| **Status** | Baseline stack, v1.0 |
| **Companion** | [architecture.md](./architecture.md) |
| **Requirements** | [SRS.md](./SRS.md) · [business-requirements.md](./business-requirements.md) |

Each choice below is tied to the requirement that drove it. The core shape — **React SPA + headless Laravel REST API, two modular monoliths** — is fixed by [ADR-0001](./adr/0001-decoupled-spa-rest-api.md).

---

## 1. Backend — Laravel

| Concern | Choice | Why |
|---------|--------|-----|
| Language / runtime | **PHP 8.4** | Typed properties, enums, readonly, property hooks, asymmetric visibility — leveraged for domain modeling ([ADR-0006](./adr/0006-runtime-baseline-laravel-12-php-84.md)) |
| Framework | **Laravel 12** | Mature ecosystem for every SRS need (auth, queues, scheduler, broadcasting, localization); Laravel 11 is EOL ([ADR-0006](./adr/0006-runtime-baseline-laravel-12-php-84.md)) |
| API style | **REST, versioned `/api/v1`** | SRS §6.2 mandates RESTful + URL-path versioning |
| API docs | **Scribe** (or L5-Swagger) → **OpenAPI 3.0** | SRS §6.2.3; the spec also generates the frontend's typed client |
| Module boundaries | **deptrac** | Enforces the modular-monolith seams ([ADR-0002](./adr/0002-modular-monolith-seams.md)) |
| Static analysis | **PHPStan / Larastan (max level)** | SRS NFR-SEC-004 (SAST in CI) |
| Auth (first-party SPA) | **Laravel Sanctum** (cookie/SPA mode) | HttpOnly/Secure/SameSite cookies + CSRF (FR-SEC-003) |
| Auth (integrations/mobile) | **Sanctum personal-access tokens** | Bearer tokens for external systems and future mobile |
| SSO | **Laravel Socialite** (OAuth) + **SAML2 / LDAP** bridge (`directorytree/ldaprecord`) | SRS FR-SEC-001.2, §6.1 |
| MFA / 2FA | **TOTP** (`pragmarx/google2fa`) + recovery codes | FR-SEC-001.3 (mandatory above Student role) |
| Rate limiting | Laravel rate limiter (Redis store) | FR-SEC-002.9, §6.2.5 (per-endpoint, configurable) |

## 2. Database & persistence

| Concern | Choice | Why |
|---------|--------|-----|
| RDBMS | **PostgreSQL** | Window functions/CTEs for GPA/standing math, JSONB for audit + doc metadata, strong replication ([ADR-0003](./adr/0003-postgresql.md)) |
| ORM / query | **Eloquent** + query builder for analytics | Standard Laravel; raw/builder for the 8 reports |
| Migrations | Laravel migrations, per-module | Each module owns and versions its own tables |
| PII at rest | **Column encryption** (Laravel encrypted casts / pgcrypto), keys in KMS | FR-SEC-004.2/.3 (AES-256, PII encrypted) |
| Replication | Postgres **synchronous streaming replica** | NFR-AVL-004/006 (RPO ≤ 15 min, real-time replication) |

## 3. Caching, queues, real-time

| Concern | Choice | Why |
|---------|--------|-----|
| Cache / sessions / rate-limit store | **Redis** | Shared state across stateless API replicas; caches derived read models |
| Queues + monitoring | **Laravel Horizon** (Redis) | Email, malware scan, PDF, GPA recalc, reminders run async (perf NFRs) |
| Scheduler | **Laravel scheduler** (cron) | SLA aging, Incomplete→FW conversion, interview/deadline reminders |
| Real-time push | **Laravel Reverb** (self-hosted WebSockets) + **Laravel Echo** on client | In-app bell ≤ 30s (NFR-PRF-009); PII stays in-house |

## 4. Documents, files, PDF

| Concern | Choice | Why |
|---------|--------|-----|
| Object storage | **MinIO** (S3-compatible), via Laravel Flysystem `s3` driver | Encrypted at rest, presigned URLs, cloud-portable, PII in-house |
| Malware scanning | **ClamAV** (queued scan on upload) | FR-ADM-003.2, NFR BR-81 |
| Key management | **HashiCorp Vault / KES** | FR-SEC-004.6 (dedicated KMS) |
| PDF generation | **Browsershot** (headless Chromium) | Renders Arabic RTL + watermark + digital signature + QR correctly (FR-TRN-001); DomPDF rejected for poor Arabic support |
| Full-text search | **PostgreSQL FTS** via **Laravel Scout** | Program tree + general search ≤ 1s (NFR-PRF-010) without an extra service; Meilisearch is the fallback at scale |

## 5. Frontend — React

| Concern | Choice | Why |
|---------|--------|-----|
| Language | **TypeScript** | Type safety end-to-end with the generated API client |
| Build tool | **Vite** | Fast builds, modern DX, static output served by ingress/CDN |
| UI library | **MUI (Material UI)** | TreeView (catalog), Stepper (application wizard), DataGrid (reports); best-documented RTL + WCAG support. Retained over Ant Design/shadcn, and themed via a token-driven E-JUST design system ([ADR-0009](./adr/0009-mui-ui-foundation-design-system.md), [design-system.md](./design-system.md)) |
| Server state | **TanStack Query** | Caching, background refetch, request dedup against the REST API |
| API client | **Generated from OpenAPI** (openapi-typescript / Orval) + **Axios** | No hand-written types; contract stays in sync ([ADR-0001]) |
| Local/UI state | **Zustand** | Lightweight; avoids Redux boilerplate for UI-only state |
| Forms + validation | **React Hook Form + Zod** | Real-time inline validation (FR-ADM-001.3); Zod mirrors backend rules |
| Routing | **React Router** | Role-aware guards; per-role dashboards (R01–R10) |
| i18n / RTL | **react-i18next** + MUI theme direction | Arabic (RTL) / English (LTR), runtime switch, no reload (NFR-USB-004/005) |
| Accessibility | **WCAG 2.1 AA** as CI gate | NFR-USB-003 |

## 6. Testing & quality

| Layer | Tool | Why |
|-------|------|-----|
| Backend unit/feature | **Pest** (on PHPUnit) | Fast, readable; per-module test suites |
| Frontend unit/component | **Vitest + React Testing Library** | Behavior-focused component tests |
| End-to-end | **Playwright** | Critical flows (application, registration, grading) across browsers |
| Backend static analysis | **PHPStan / Larastan** + **deptrac** | Type safety + module-boundary enforcement |
| Frontend lint | **ESLint** (+ `eslint-plugin-boundaries`) + **Prettier** | Feature-module seams + consistent formatting |
| Formatting (PHP) | **Laravel Pint** | Consistent PHP style |

## 7. Infrastructure & operations

| Concern | Choice | Why |
|---------|--------|-----|
| Containerization | **Docker** | Reproducible images for every service |
| Orchestration | **Kubernetes** (HA Docker Swarm acceptable) | Autoscaling + 60s failover (NFR-AVL-005), 5,000 concurrent users ([ADR-0004](./adr/0004-self-hosted-on-prem-hosting.md)) |
| Hosting | **Private cloud / on-premise** | Data-residency / FERPA posture |
| Web/TLS | **nginx ingress, TLS 1.3** | FR-SEC-001.7, FR-SEC-004.1 |
| CI/CD | **GitHub Actions** (or GitLab CI) | Runs tests, PHPStan, deptrac, ESLint, **SAST** (NFR-SEC-004), builds images |
| Metrics | **Prometheus + Grafana** | Uptime/perf SLO monitoring (NFR-AVL/PRF) |
| Error tracking | **Sentry** (self-hostable) | Frontend + backend error visibility |
| Logging & audit | Centralized logs + append-only **audit trail** | 7-year retention (NFR-SEC-005) |

## 8. External integrations (SRS §6.1)

| Integration | Direction | Protocol | Stack touchpoint |
|-------------|-----------|----------|------------------|
| University LDAP/AD | Inbound | LDAP / SAML 2.0 | Socialite + ldaprecord (Identity module) |
| Email service | Outbound | SMTP / REST | Laravel Mail + queued jobs (Notifications) |
| SMS gateway | Outbound | REST | Notification channel driver |
| Document storage | Bidirectional | S3 API | MinIO via Flysystem |
| Financial system | Bidirectional | REST | HTTP client + hold-check service |
| Antivirus scanner | Inbound | CLI/API | ClamAV (queued) |
| PDF generation | Internal | Library | Browsershot |

---

## Stack at a glance

```
Frontend   React + TypeScript · Vite · MUI · TanStack Query · Zustand
           React Hook Form + Zod · React Router · react-i18next
           OpenAPI-generated client · Vitest + Playwright

Backend    PHP 8.4 · Laravel 12 · REST /api/v1 (OpenAPI 3.0)
           Sanctum + Socialite/SAML/LDAP + TOTP MFA
           deptrac · PHPStan/Larastan · Pest · Horizon · Reverb

Data       PostgreSQL (primary + sync replica) · Redis · MinIO
           Vault/KES (KMS) · ClamAV · Browsershot

Platform   Docker · Kubernetes · nginx/TLS 1.3 · private cloud/on-prem
           GitHub Actions (+SAST) · Prometheus/Grafana · Sentry
```

*See [architecture.md](./architecture.md) for how these pieces fit together and [adr/](./adr/) for the reasoning behind the load-bearing choices.*
