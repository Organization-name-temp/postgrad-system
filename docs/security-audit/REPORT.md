# PRS security audit and exploit report

Audit date: 2026-09-28. Code baseline: `44807f61223dbbebca8551c0f8844cb6fd24db78`, including the existing working tree. Target: the authorized local E-JUST Postgraduate Registration System. This report preserves the pre-fix audit baseline. See [remediation and retest results](REMEDIATION.md) for the subsequent fixes on main.

## Result

The strongest findings are an Admissions faculty-scope bypass and stale workflow writes. A Dean can retrieve another Faculty's Application by numeric ID, obtain its private-document handles, download those documents, and reject that Application through the general screening endpoint. Two screening handlers that bind the same initial state can also record incompatible decisions and overwrite a terminal rejection.

A separate, inert dependency PoC confirmed code injection in installed Orval 8.20.0 using the project's React Query/Axios generation mode. Its prerequisite is attacker-controlled OpenAPI input; ordinary Applicant API input has not been shown to reach that input.

Accepted ADR-0005 deliberately defers production hardening. Weak passwords, mock email verification, absence of malware scanning, debug responses, HTTP transport, and limited authentication defenses must therefore be assessed as deployment blockers or accepted local risks, rather than silently labelled unfinished production requirements. Full RBAC remains a retained requirement under that ADR.

## Scope and method

- Read canonical terminology, codebase ownership, SRS/security requirements, and relevant accepted ADRs, particularly 0005, 0008, 0015, 0016, and 0046.
- Reviewed authentication/session setup, role and scope enforcement, Admissions workflow writes, document validation/storage, Records bearer verification, export/query handling, frontend rendering/downloads, dependency manifests and generation configuration.
- Exercised the real Laravel HTTP kernel with synthetic PostgreSQL fixtures and fake private storage. Exploit cases run separately from the normal test suites.
- Sent six bounded requests to the existing local HTTP server at `127.0.0.1:8000`.
- Queried current Composer/npm advisory feeds and reproduced one dependency flaw in temporary generated files using an in-memory marker.
- Ran existing tests for Identity authentication/authorization, Admissions Dean review and uploads, Grading oversight, Enrollment decisions, Notifications, and all Records feature tests, including certification concurrency.

No destructive load testing, actual malware, external-system attacks, or real Applicant records were used. The test suite refreshes the disposable `prs_test` database; a pre-refresh guard rejects a different default connection/database. The running application's `prs` database was not reset or seeded. Existing release-readiness edits were preserved.

## Finding index

Severity describes impact if the application is reachable by the relevant attacker, not exposure of this loopback deployment. No numeric CVSS scores were invented.

| ID       | Finding                                                            | Severity                              | Evidence/classification                                                |
| -------- | ------------------------------------------------------------------ | ------------------------------------- | ---------------------------------------------------------------------- |
| AUDIT-01 | Dean reads outside-Faculty Application PII, timeline and documents | High                                  | Confirmed through HTTP kernel                                          |
| AUDIT-02 | Dean rejects outside-Faculty Application through screening         | High                                  | Confirmed through HTTP kernel                                          |
| AUDIT-03 | Login guesses are not throttled                                    | Medium                                | 30 failed guesses accepted; hardening deferred by ADR-0005             |
| AUDIT-04 | Stateless registration commits records and then returns 500        | Low                                   | Confirmed through HTTP kernel; state/error inconsistency               |
| AUDIT-05 | Stale screening handler overwrites a terminal rejection            | High                                  | Deterministic handler interleaving; parallel socket race not run       |
| AUDIT-06 | Orval OpenAPI path injects executable generated code               | High under input-control prerequisite | Inert execution confirmed; upstream advisory rates flaw Critical       |
| AUDIT-07 | General screening rejection bypasses later review-stage authority  | High                                  | Confirmed by rollback-only HTTP-kernel probe during remediation review |

### AUDIT-01 — Faculty scope disappears on direct reads

**Attacker:** an authenticated User holding only the Dean Role, assigned to Faculty A. **Victim:** an Applicant whose primary Program belongs to Faculty B.

`ApplicationCollection::scoped()` excludes Faculty B from the Dean's screening queue. However, `ApplicationScreeningController::show()` returns any bound Application once the Role's `application.screen` Permission passes. `timeline()` and `DocumentController::download()` likewise check owner-or-Permission without Faculty scope.

Source: [ApplicationCollection](../../backend/modules/Admissions/Internal/Support/ApplicationCollection.php), [screening controller](../../backend/modules/Admissions/Internal/Http/Controllers/ApplicationScreeningController.php), [document controller](../../backend/modules/Admissions/Internal/Http/Controllers/DocumentController.php), and [route definitions](../../backend/modules/Admissions/Public/Http/routes.php).

**Executed baseline PoC (the test now asserts remediation):** `AUDIT-01` in [SecurityAuditPoCTest.php](../../backend/tests/SecurityAudit/SecurityAuditPoCTest.php).

1. Create two Programs in distinct Faculties; assign a Dean to the first.
2. Create an Application and a private PDF in the second.
3. As the Dean, `GET /api/v1/applications` returns `200`, `meta.total = 0`.
4. `GET /api/v1/applications/{victim_id}` returns `200`, the victim's National ID, and document ULID.
5. `GET /api/v1/documents/{returned_ulid}` returns `200` and the private fixture bytes.
6. `GET /api/v1/applications/{victim_id}/timeline` returns `200`.

Application IDs are numeric, so a private document's opaque ULID does not prevent this chain: the unscoped detail endpoint reveals it. No ULID guessing was necessary.

**Remediation:** use one consistent Application access policy for collection, detail, timeline, and document endpoints. Permit the owner for appropriate self reads, Student Affairs for university-wide review, R09/R10 for their accepted override, and a Dean only within assigned Faculties. Preserve the union for multi-role Users. Add negative tests for outside scope, missing assignments and missing Program references, plus positive tests for intended SA/admin overrides. Per-request object authorization follows [OWASP authorization guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html).

### AUDIT-02 — Screening writes bypass the Dean's Faculty boundary

The general screening route admits Deans through `can:application.screen`. `ApplicationScreeningController::screen()` never checks the bound Application's Faculty. The dedicated Dean review handler's scope check does not protect this alternate route.

**Executed PoC:** `AUDIT-02` creates the same outside-Faculty fixture and submits:

```http
POST /api/v1/applications/{outside_faculty_application_id}/screen
Content-Type: application/json

{"action":"reject","note":"AUDIT: unauthorized cross-faculty rejection"}
```

With the Dean's authenticated context, the response is `200`, `data.status = rejected`; PostgreSQL contains the rejected status. An attributed transition/notification does not make this cross-scope decision authorized. The production equivalent would require a valid session and CSRF token.

**Remediation:** apply the common scope policy before any screening transition, inside the transaction if the scope-driving fields can change. Verify that out-of-scope approve/return/reject all fail without changing state, history, or notifications. Consider whether Dean screening itself is intended; the current permission matrix grants it, so do not remove that Permission implicitly.

### AUDIT-03 — Login is an unthrottled guessing and audit-write surface

`POST /api/v1/login` has no throttle middleware; `LoginController` attempts authentication and writes one Audit Log row for every credential failure. Registration and resend routes have explicit limiters, but login does not.

**Executed PoC:** a fresh fixture User receives 30 successive incorrect password attempts. All return `422`; zero return `429`; 30 `login_failed` rows are created. This is bounded evidence of absent throttling, not a claim of unlimited throughput or a demonstrated successful password crack.

**Impact:** credential guessing remains possible, and rejected requests incur password-verification and database-write costs. A valid stateful browser must still satisfy CSRF. CSRF does not provide a guessing limit to a client that can obtain its own token.

**Remediation:** add account/IP-aware login limits, normalize login identifiers, define recovery behavior, and monitor rejected attempts. Avoid permanent lockout that attackers can use against victims. Apply [OWASP authentication guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html). ADR-0005 explicitly defers account lockout/auth hardening, so this is a known-scope hardening risk rather than a retained-RBAC regression.

### AUDIT-04 — Account creation commits before session setup can fail

`RegistrationController` commits User + Draft Application, then calls `Auth::loginUsingId()` and `$request->session()->regenerate()`. Without a recognized stateful Origin/Referer, Sanctum does not attach a session store. A valid registration therefore commits and then throws `Session store not set on request`.

**Executed PoC:** send valid JSON to `/api/v1/register` without Origin/Referer during an open Admission Cycle. Observe `500`, while the User and Application exist. Compare with the stateful registration test, which returns `201` for the same class of input.

**Impact:** an apparent failed registration reserves the email and consumes persistent rows. The valid registration action itself is public and already allows claiming an unused email, so this finding does not establish a distinct account-takeover or victim-lockout exploit. It is a low-severity consistency flaw and a source of unnecessary debug exposure.

**Remediation:** reject unsupported stateless requests before committing or establish the session consistently. Return a predictable documented response; add a regression case for absence of Origin/Referer. Review successful stateless login for the same post-authentication assumption, without claiming it was dynamically exploited here.

### AUDIT-05 — Transition validation trusts a stale bound model

`Application::transitionTo()` checks `$this->status`, writes the new value, and appends a transition. The screening controller wraps writes in a transaction but neither reloads/locks the Application nor performs a conditional status update. Transaction atomicity alone does not serialize competing decisions.

**Executed PoC:** `AUDIT-05` deterministically reproduces two handlers' binding/write order:

1. Two model bindings both read `under_sa_review`.
2. The first screening request rejects the Application through the HTTP kernel.
3. The second screening handler executes approve with its previously bound model.
4. Final state becomes `under_dean_review`, overwriting `rejected`.
5. History contains two outgoing transitions from `under_sa_review`, including the rejection.

The second handler was invoked through Laravel dependency injection with a synthetic authorized Request and the stale route-binding model. This proves that the handler accepts the stale state. It does **not** measure how often two independent HTTP requests win the race; no claim of a parallel socket race or duplicated admission acceptance is made.

**Impact:** an authorized reviewer, or overlapping legitimate reviewers, can undermine workflow integrity and generate conflicting decisions/notifications.

**Remediation:** lock and re-read the Application inside the transaction before state validation, or use a conditional update/version guard and reject the loser with `409`. Ensure transition history and notifications follow only the winning decision. Audit other Admissions methods using the same transition primitive; their exploitation was not demonstrated. Add a competing PostgreSQL-session test similar to the existing Records certification concurrency tests.

### AUDIT-06 — Generated query keys execute OpenAPI path content

Installed Orval is `8.20.0`. The repository generates React Query/Axios endpoints and Zod schemas from `backend/openapi.yaml` using `frontend/orval.config.ts`. The upstream [path-injection advisory](https://github.com/advisories/GHSA-fg9p-mrxr-hvq7) identifies versions below `8.21.0` as affected; its severity is Critical.

**Executed PoC:** [orval-poc.mjs](orval-poc.mjs) supplies a synthetic OpenAPI path:

```text
/audit/` + (globalThis.__PRS_AUDIT_MARKER__ = "executed") + `/end
```

It generates a temporary React Query/Axios client, transpiles it, and invokes the exported query-key function. No HTTP request is made. Observed result:

```json
{
  "package": "8.20.0",
  "client": "react-query/axios",
  "marker": "executed",
  "triggered": true
}
```

**Prerequisite and limit:** the attacker must influence the specification used by generation, for example through an imported untrusted schema or a repository change subsequently generated/executed. No path from Applicant API fields to this specification was found. Generated code injection is confirmed; shell execution, secret access, and compromise of the committed PRS client were not attempted or demonstrated. This raises build-chain review risk and should not be described as unauthenticated PRS API RCE.

**Remediation:** update Orval to a currently advisory-clean version and regenerate/review artifacts. `8.21.0` fixes this particular advisory, but other advisories in the saved scan affect later ranges too; it is not a blanket safe-version recommendation. Keep specification changes and generated code under review, and enforce dependency auditing in CI.

### AUDIT-07 — Screening rejection bypasses later-stage authority

**Additional finding during remediation review:** the general screening endpoint documents that every action requires `under_sa_review`. Its reject branch called the shared model rejection transition, which also permits rejection from `under_dean_review`, `interview_completed`, `conditionally_accepted` and `waitlisted`. A User holding only Student Affairs could therefore make these later-stage terminal decisions through `POST /api/v1/applications/{id}/screen`, bypassing the dedicated Dean decision route.

**Severity: High.** This changes another authority's workflow stage into an irreversible rejection. SRS §4.2.5–4.2.6 and the screening endpoint's existing contract separate Student Affairs screening from later review.

**Executed PoC:** [screening-stage-poc.php](screening-stage-poc.php) creates synthetic Student Affairs/Application fixtures and invokes the actual HTTP kernel. It never migrates or seeds; all fixture records and notifications are rolled back. PostgreSQL sequences may advance in the disposable test database. [Before-fix evidence](evidence/screening-stage-red.txt) shows HTTP 200, `rejected` and one transition in all four later stages. [Retest evidence](evidence/screening-stage-green.txt) shows HTTP 409 with unchanged statuses and zero transitions after the locked stage check.

```bash
DB_DATABASE=prs_test php docs/security-audit/screening-stage-poc.php
```

The script refuses any database other than `prs_test`. It expects the fixed behavior by default; `--expect-vulnerable` checks the historical 200 outcome on vulnerable code.

## Accepted local risks and deployment blockers

These observations are separate from the scope bugs above.

| Observation                         | Evidence                                                                                                                 | Consequence before using real data                                                                                                                                                            |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Weak passwords                      | `DEFERRED-01`: `aaaaaaaa` registers successfully                                                                         | Replace the local minimum-8 policy with the agreed production password/authentication policy                                                                                                  |
| Mock email verification             | `DEFERRED-02`: authenticated Applicant obtains `verification_url` from resend and verifies without a mailbox             | This verifies possession of an app-provided link, not control of the email address; bind a real delivery service before treating it as email ownership proof                                  |
| Uploads preserve active PDF content | `DEFERRED-03`: inert PDF JavaScript action bytes are accepted, stored and returned unchanged                             | MIME validation is not content sanitization/malware scanning; introduce the approved scanner/quarantine and document handling policy. No PDF viewer execution or browser XSS was demonstrated |
| Public debug traces                 | Live invalid verification-token request returns `404` with exception, absolute source path and 48 trace frames           | Disable debug before exposure; return minimal errors and retain diagnostics server-side                                                                                                       |
| Local transport/session defaults    | `.env.example`: HTTP, debug enabled, 120-minute session lifetime, no explicit Secure cookie, session encryption disabled | Reconcile with production requirements and configure TLS/cookie/session policy                                                                                                                |
| Database development defaults       | Compose source publishes `5432:5432` and uses fixed demo credentials                                                     | Bind development networking deliberately and rotate/use protected credentials for deployment. Actual runtime remote PostgreSQL reachability was not tested                                    |

The email link returned to the session/logs and absent scanner are explicit ADR-0005 stubs. The PDF case used only an inert marker; it does not prove uploaded PHP execution. Private files remain on the local private disk.

Public full issued-Transcript verification is **not** listed as an IDOR: ADR-0046 explicitly permits bearer-link access to identity and academic data, including the National ID/passport number, without login or automatic expiry. Revocation narrows the response under ADR-0047. This is an accepted privacy-sensitive design that operators must understand, not a newly discovered authorization bypass.

## Dependency advisory inventory

Raw baseline scan outputs are preserved: [Composer](evidence/composer-audit.json), [npm](evidence/npm-audit.json). Both commands returned exit status `1` because they found advisories, not because a clean audit passed.

- Composer: **16 advisories across 2 installed packages**: Guzzle `7.14.0` (6) and CommonMark `2.8.2` (10). The listed issues include request/cookie handling, parser resource exhaustion and extension-related XSS. No user-driven outbound Guzzle or Markdown rendering exploit path was established in the reviewed application code. Treat package matches as dependency findings, not verified application SSRF/XSS.
- npm: **13 affected package entries**: 1 Critical, 9 High, 3 Moderate. This includes transitive entries; it is not 13 independent remotely exploitable PRS defects. See the saved JSON for all advisory URLs and affected ranges.
- Orval injection was reproduced as above. Other Orval advisories, including [Zod default injection](https://github.com/advisories/GHSA-w727-8j6c-2rj4), were not independently reproduced.
- React Router `7.18.1` is matched by an [RSC-mode CSRF advisory](https://github.com/advisories/GHSA-qwww-vcr4-c8h2). This repository is a Vite browser SPA, with no React Router RSC action server found; that advisory's remote exploit mode was not demonstrated here.
- Many remaining npm matches concern development/build tools. A vulnerable version match is still actionable maintenance evidence, but reachability and the processing of untrusted inputs determine exploitability.

No package versions or lockfiles were changed during the initial audit; the subsequent updates and clean scans are documented in [REMEDIATION.md](REMEDIATION.md).

## Controls that held and coverage limits

Exploit/control replay: **11 tests passed, 68 assertions**. Passing here means the described exploit or control behavior was reproduced; it does not mean the system passed a security audit. See [PoC output](evidence/poc-results.txt). The standalone Orval PoC also reproduced its in-memory marker. PHP PoC formatting passed Pint; this report and the JavaScript PoC passed Prettier checks. Saved JSON evidence parses, and report artifact links resolve.

The exploit/control suite includes positive evidence that unrelated Applicants cannot read another Application/document, delete another Applicant's document, or enter administration. Injected registration roles and `email_verified_at` are ignored. Invalid verification signatures are refused. SQL-looking sort fields and HTML content disguised with a `.pdf` filename are rejected. These narrow cases do not establish universal immunity to injection or IDOR.

Live HTTP observations in [http-controls.json](evidence/http-controls.json): unauthenticated `/me` returns `401`, including with an untrusted Origin; stateful login without CSRF returns `419`; unknown transcript bearer tokens return `404`; `/.env` returns `404`. Wildcard CORS was observed, but no credentialed CORS exfiltration was demonstrated, and wildcard alone is not evidence of that exploit.

Existing security-focused regressions: **143 tests passed, 938 assertions**, including Records feature/concurrency tests. These tests passing does not refute the alternate Admissions paths uncovered here. See [regression output](evidence/security-regression-results.txt).

Limitations: this is a time-bounded source-assisted local audit, not a certification. Not every route/role/object combination was exhaustively fuzzed. Browser exploit execution, full-stack Playwright, destructive load tests, a live Admissions socket race, actual malware detection, deployment/network penetration, and backup/host access controls were not tested. No successful SQL injection, browser XSS, session theft, or remote application RCE was demonstrated. Frontend/backend full gates were not run because application behavior and generated contracts were unchanged; the targeted tests, PoCs, and artifact formatting checks were used instead.

## Reproduce

Prerequisites: installed backend/frontend dependencies, PHP 8.4, Node, PostgreSQL, normal local environment settings, and disposable `prs_test`. The PoCs assert vulnerable behavior, so they should fail after fixes. They are excluded from the normal PHPUnit Feature/Unit suites to avoid treating exploit success as a security regression gate.

From the repository root:

```bash
cd backend
vendor/bin/pest tests/SecurityAudit/SecurityAuditPoCTest.php --colors=never
```

Then from the repository root:

```bash
node docs/security-audit/orval-poc.mjs
python3 docs/security-audit/http-probes.py
```

The HTTP probes expect the existing local backend on port 8000. They overwrite their own saved probe evidence. The Orval PoC cleans up its OS temporary directory and does not edit `backend/openapi.yaml` or the committed generated client.

To refresh advisory evidence:

```bash
cd backend
composer audit --format=json
```

```bash
cd frontend
npm audit --json
```

## Recommended order

1. Fix the shared Application scope policy and apply it to direct reads, files, timelines and screening writes; preserve approved admin and multi-role behavior.
2. Serialize Admissions state transitions and add a real competing-session regression.
3. Update/review affected dependencies, prioritizing Orval; regenerate and audit the client before trusting newly generated code.
4. Make registration's session requirements explicit and prevent commit-then-error responses.
5. Before any real-data deployment, revisit ADR-0005 and implement login limits, real email verification, file scanning, TLS, minimal error responses and deployment-specific security configuration.
