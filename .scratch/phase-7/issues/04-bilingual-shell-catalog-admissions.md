# 04 — Bilingual polish: shell, Catalog and Admissions

**What to build:** Users move through authentication, navigation, Dashboard, Catalog and Admissions in either language with translated messages, readable mixed-direction content and usable responsive layouts. Fix concrete defects found in these surfaces and record their disposition.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] Audit registration/login, authenticated shell, navigation, notification bell, Dashboard, Catalog management/tree/curriculum/version surfaces and the Applicant/staff Admissions flows in EN/LTR and AR/RTL, light and dark themes.
- [ ] Record covered surfaces, discovered defects, fixes and any explicit deferrals in a reproducible audit record. If a surface has no defects, record the checked language/theme states instead of manufacturing changes.
- [ ] Fix demonstrated missing translations, raw translation keys, untranslated statuses/errors, clipped labels, directional spacing/alignment and mixed-script ordering. Add backend messages in EN/AR together and keep both frontend dictionaries aligned.
- [ ] Verify dynamic validation/reviewer messages and typed notifications through their actual rendering paths. Preserve existing application transitions, authorization, Dashboard permission composition and input values during language changes.
- [ ] Course codes, University IDs, emails, dates and numbers remain readable alongside Arabic labels. Branding is not mirrored; styles use theme/design tokens and colour is never the sole signal.
- [ ] Tables, forms, dialogs, menus and navigation remain usable at supported narrow/wide layouts in all four language/theme combinations. Preserve keyboard focus, control labels and existing responsive behaviour.
- [ ] Add targeted regressions for concrete defects at the highest existing seam: focused Vitest/RTL interactions, Pest for changed backend message responses, and existing browser flows where a real integration behaviour changed. Do not add broad snapshots or repeat all workflow tests for cosmetic fixes.
- [ ] Run narrow tests and affected package checks; record any unrun gate with a concrete reason. No redesign, new academic behaviour, component-library migration, speculative asset reconstruction or unspecified niceties are included.

## Comments

This ticket audits existing shipped surfaces and can proceed while the Phase 4.6 report prerequisites remain outstanding. New Reports carry their bilingual acceptance in #01–#03.
