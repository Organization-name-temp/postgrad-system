# 06 — Structured & validated personal-info inputs (ID type, phone, nationality)

**What to build:** Three error-prone free-text fields become structured, validated inputs. The applicant declares whether they hold a **National ID** or a **Passport** (validated to that format); enters their **phone** as country + number (stored E.164, digit count checked per country); and picks **nationality** from a searchable list rather than typing it. All validated on both client (Zod) and server. Introduces the **Identification Type** concept (CONTEXT.md).

**Blocked by:** 05 — React Hook Form + Zod foundation.

**Status:** ready-for-agent

- [ ] An Identification Type selector is added (new `id_type`): National ID = exactly 14 digits, Passport = alphanumeric 6–9; enforced client + server; the identifier remains unique within the Admission Cycle.
- [ ] Phone is captured via a country-selector + national-number component, validated per country, and stored as E.164; the server re-validates the E.164 shape.
- [ ] Nationality is a searchable ISO-3166 Autocomplete (EN/AR labels), stored as the alpha-2 code, and server-validated against the code list.
- [ ] Migrations add `id_type` (and its Step-1 snapshot); `nationality`/`phone` now hold code / E.164. `openapi.yaml` + the Orval client are regenerated (`api:check` clean).
- [ ] Tests: each field's boundary as a `422` (14-digit vs 13/15; bad passport length; malformed E.164; nationality not in the list). Gates green.
