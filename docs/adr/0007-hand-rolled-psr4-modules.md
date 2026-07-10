# Hand-rolled PSR-4 modules with per-module service providers

**Status:** accepted

The backend is a modular monolith of ~9 bounded-context modules under
`backend/modules/<Name>/{Public,Internal}` ([ADR-0002](./0002-modular-monolith-seams.md)).
Something has to teach Laravel where those modules live and how each one registers its
routes, migrations, policies, and bindings. We do this **by hand**: a single PSR-4 map
(`"Modules\\": "backend/modules/"`) in `composer.json`, and one thin
`ModuleServiceProvider` per module, auto-discovered by Laravel's package discovery. No
third-party module framework. This ADR exists so a future reader does not add
`nwidart/laravel-modules` "to make modules easier" and quietly change the structure
`deptrac` and the whole team depend on.

## Considered options

- **Hand-rolled PSR-4 + per-module providers (chosen).** The module layout is entirely
  ours, so the `Public`/`Internal` seam is exactly what [ADR-0002](./0002-modular-monolith-seams.md)
  describes and exactly what `deptrac` reasons about — no framework conventions to fight.
  Nothing is hidden: a reader sees the composer map and the provider and knows how a
  module boots. Cost is a little boilerplate per module (one provider), which a stub/
  generator absorbs.
- **`nwidart/laravel-modules` (rejected).** Batteries-included generators and
  auto-registration, less boilerplate. But it imposes its own module directory shape
  (its `app/`, `routes/`, `config/` convention), which collides with our `Public`/`Internal`
  seam; we would spend effort bending it back, and its magic obscures the boundary that
  is the whole point.
- **Composer path-repository packages (rejected).** Each module a real Composer package
  with its own `composer.json`. Strongest isolation and a true dependency graph, but
  heavy per-module ceremony (versioning, `composer require`, install churn) that slows
  iteration on a single-team learning build with no independent-release need.

## Consequences

- Adding a module is a mechanical three steps — create `modules/<Name>/{Public,Internal}`,
  add a `ModuleServiceProvider`, add the module to the `deptrac` ruleset — cheap enough to
  script later if it becomes tedious.
- `deptrac` layers are defined by namespace regex over `Modules\*\Public` and
  `Modules\*\Internal`; because the layout is ours, those collectors stay simple.
- If a module is ever extracted into its own service, promoting its `Public` facade to an
  HTTP boundary does not require unwinding a framework's module abstraction first.
- We can still adopt a generator package later purely for scaffolding without changing the
  runtime wiring, because the wiring is plain Laravel.
