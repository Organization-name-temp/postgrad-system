# Decoupled React SPA + headless Laravel REST API

**Status:** accepted

We build the frontend as a standalone React SPA and the backend as a headless Laravel REST API (`/api/v1`, OpenAPI 3.0, token/cookie auth), rather than coupling them through Inertia.js into a single server-rendered app. The SRS independently mandates a versioned REST API with OpenAPI docs, OAuth2/JWT bearer auth, per-endpoint rate limiting, `Accept-Language` negotiation, and external/mobile clients — a headless API satisfies all of these directly, whereas Inertia would require building and maintaining a second API surface anyway.

## Considered options

- **Decoupled SPA + REST API (chosen).** One contract serves the web app, external integrations, and future mobile.
- **Inertia.js monolith (rejected).** Simplest to build, but no client-facing API contract; the SRS's integration and mobile requirements would force a parallel API regardless.
- **Hybrid Inertia + separate API (rejected).** Two auth flows and two contracts to secure and maintain, justified only by a near-term native mobile app that is not committed.

## Consequences

- The OpenAPI spec is the single source of truth; the frontend consumes a typed client generated from it.
- Authorization must be enforced server-side on every endpoint and never trusted from the SPA.
- Frontend and backend can be built, versioned, and scaled independently.
