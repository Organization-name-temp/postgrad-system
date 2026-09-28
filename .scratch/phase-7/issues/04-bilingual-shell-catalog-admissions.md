# 04 — Bilingual polish: shell, Catalog and Admissions

**What to build:** Users move through authentication, navigation, Dashboard, Catalog and Admissions in either language with translated messages, readable mixed-direction content and usable responsive layouts. Fix concrete defects found in these surfaces and record their disposition.

**Blocked by:** None — can start immediately.

**Status:** implemented — final verification pending

- [x] Audit registration/login, authenticated shell, navigation, notification bell, Dashboard, Catalog management/tree/curriculum/version surfaces and the Applicant/staff Admissions flows in EN/LTR and AR/RTL, light and dark themes.
- [x] Record covered surfaces, discovered defects, fixes and any explicit deferrals in a reproducible audit record. If a surface has no defects, record the checked language/theme states instead of manufacturing changes.
- [x] Fix demonstrated missing translations, raw translation keys, untranslated statuses/errors, clipped labels, directional spacing/alignment and mixed-script ordering. Add backend messages in EN/AR together and keep both frontend dictionaries aligned.
- [x] Verify dynamic validation/reviewer messages and typed notifications through their actual rendering paths. Preserve existing application transitions, authorization, Dashboard permission composition and input values during language changes.
- [x] Course codes, University IDs, emails, dates and numbers remain readable alongside Arabic labels. Branding is not mirrored; styles use theme/design tokens and colour is never the sole signal.
- [x] Tables, forms, dialogs, menus and navigation remain usable at supported narrow/wide layouts in all four language/theme combinations. Preserve keyboard focus, control labels and existing responsive behaviour.
- [x] Add targeted regressions for concrete defects at the highest existing seam: focused Vitest/RTL interactions, Pest for changed backend message responses, and existing browser flows where a real integration behaviour changed. Do not add broad snapshots or repeat all workflow tests for cosmetic fixes.
- [x] Run narrow tests and affected package checks; record any unrun gate with a concrete reason. No redesign, new academic behaviour, component-library migration, speculative asset reconstruction or unspecified niceties are included.

## Comments

This ticket audits existing shipped surfaces and can proceed while the Phase 4.6 report prerequisites remain outstanding. New Reports carry their bilingual acceptance in #01–#03.

## Executable disposition

Implemented on `phase-7-academic-reports-bilingual` against fixed baseline
`b3a3ae7b71538c36230985270de2b73bc2f92088`. Focused red/green evidence,
owner/API/UI verification and prerequisite dispositions are recorded in
[the Phase 7 tracker](../tracker.md). The single consolidated
[Standards/Spec review](../review.md) has all four actionable findings resolved.
The final backend gate passed (1,334 tests / 8,458 assertions); frontend static
checks and 684 unit tests passed. The complete serial browser stage is still
running. This disposition does not claim phase acceptance or human review.
