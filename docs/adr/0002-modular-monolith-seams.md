# Modular monolith with facade + event seams over one database

**Status:** accepted

The backend is a single deployable Laravel application divided into ~9 bounded-context modules (Identity, Admissions, Catalog, Enrollment, Grading, Academic Standing, Records + supporting Notifications, Calendar, Reporting). Each module owns its own tables and exposes a **public service facade**; modules never touch each other's tables directly. Synchronous needs go through facades, cross-module reactions go through **in-process domain events**. All modules share one PostgreSQL database so cross-module transactions and referential integrity remain simple. `deptrac` fails the build if a module reaches into another module's internals.

## Considered options

- **Facades + events, one DB (chosen).** Real boundaries with monolith operational simplicity.
- **Shared models, direct Eloquent access (rejected).** Fastest to write, but "modular" becomes cosmetic and the boundaries erode into a big ball of mud.
- **Strict schema-per-module, events only (rejected).** Microservice-ready but imposes eventual consistency and removes cross-module transactions for a single team that doesn't need it yet.

## Consequences

- Exemption, Transfer, and Waiver live inside **Enrollment** because all three mutate the same student credit-hour accounting.
- The **Credit-Hour Summary** and **Academic Standing** are *derived* read models computed from authoritative records (SRS Appendix D), cached in Redis, invalidated by domain events — never stored as separately-editable balances.
- A module can later be extracted into its own service by promoting its facade to an HTTP boundary, because callers already depend only on the facade.
