# 02 — Module seams + quality gates in CI

**What to build:** The backend is organized into boundary-enforced modules, and both tiers gain one-command quality gates that fail on architecture or style violations — so the modular monolith cannot erode as later phases add modules. See [ADR-0002](../../../docs/adr/0002-modular-monolith-seams.md) and [ADR-0007](../../../docs/adr/0007-hand-rolled-psr4-modules.md).

**Blocked by:** 01.

**Status:** ready-for-agent

- [ ] Backend modules live under a `Modules\` PSR-4 namespace, each booted by an auto-discovered `ModuleServiceProvider`; the **Identity** module exists with a `Public/` and an `Internal/` folder.
- [ ] The health endpoint is served from within a module, demonstrating the module route/registration pattern.
- [ ] deptrac fails the build when code outside a module imports that module's `Internal/`, and passes for imports of its `Public/`.
- [ ] `eslint-plugin-boundaries` forbids cross-feature imports under `frontend/src/features/*`.
- [ ] PHPStan/Larastan and Pint run clean on the backend; ESLint, Prettier, and `tsc` run clean on the frontend.
- [ ] `composer gate` runs Pint + PHPStan/Larastan + deptrac + Pest; `npm run gate` runs ESLint (incl. boundaries) + Prettier + `tsc` + Vitest + the Playwright smoke.
- [ ] A `.github/workflows/ci.yml` invokes both gate scripts on push and PR.
- [ ] Verification: a deliberately introduced boundary violation is shown to fail the relevant gate, then reverted.
