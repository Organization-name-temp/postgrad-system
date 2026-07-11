# 06 — Bilingual i18n loop (EN/AR + RTL, persisted, negotiated)

**What to build:** A user can switch the whole interface between English (LTR) and Arabic (RTL) at runtime without reload, have that choice remembered across logins, and receive server validation/error messages in their chosen language. [ADR-0005](../../../docs/adr/0005-localhost-learning-build.md) pulled i18n into Phase 0 precisely so this loop is proven early.

**Blocked by:** 04. *(Parallel with 05 — needs a user, not permissions.)*

**Status:** ready-for-agent

- [ ] react-i18next is configured with per-feature namespaces plus a shared `common`, with EN and AR resource files.
- [ ] A language switcher flips the entire UI between EN/LTR and AR/RTL at runtime (MUI theme direction flips) with no page reload.
- [ ] `users.preferred_locale` persists the choice via `PATCH /api/v1/me`; on the next login the UI opens in the saved language.
- [ ] An `Accept-Language` middleware sets the Laravel locale; a validation error comes back localized (EN vs AR) according to the header.
- [ ] Pest tests cover the `preferred_locale` persistence round-trip and the localized-error negotiation.
