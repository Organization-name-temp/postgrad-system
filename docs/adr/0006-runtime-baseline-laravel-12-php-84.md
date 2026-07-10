# Runtime baseline: Laravel 12 + PHP 8.4

**Status:** accepted (supersedes the "Laravel 11 / PHP 8.3" pin in
[architecture.md](../architecture.md) and [tech-stack.md](../tech-stack.md))

We build on **Laravel 12** and **PHP 8.4**, not the Laravel 11 / PHP 8.3 named
throughout the baseline docs. Those docs were written before **Laravel 11 reached
end of security support on 12 March 2026**; starting a brand-new build in mid-2026 on
an unsupported framework is not sensible. This ADR exists so a future reader does not
"correct" the code back to Laravel 11 to match the older prose.

## Considered options

- **Laravel 12 + PHP 8.4 (chosen).** Fully supported; the whole toolchain (Sanctum,
  Scribe, Larastan, Pest, deptrac) has been compatible for a long time, so no
  early-adopter package gaps. PHP 8.4 adds property hooks and asymmetric visibility,
  which suit the readonly/typed domain modeling this project leans on.
- **Laravel 13 + PHP 8.4 (rejected for now).** Current stable (Mar 2026), longest
  support runway, but only a few months old — not worth shaking out early tooling gaps
  on a learning build. Easy to move to later.
- **Laravel 11 + PHP 8.3 (rejected).** Matches the existing docs but is out of security
  support; wrong default for a fresh 2026 build.

## Consequences

- The framework major is a pin everything sits on; changing it mid-build is painful, so
  it is fixed now.
- `architecture.md` and `tech-stack.md` still say "Laravel 11 / PHP 8.3" in places; this
  ADR is the authority. Those lines are updated to point here.
