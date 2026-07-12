# 04 — StatusChip presentational component

**What to build:** A single reusable status chip that later phases use for every domain status. It
is purely presentational — it takes a tone, a variant, an optional icon, and a label, and **always
renders the label** so colour is never the only signal. It encodes the design rules (non-counting →
outlined, negative → filled) but knows no domain status names; each later phase supplies its own
status→chip mapping from inside its feature.

Props contract (a decision, not a prototype):

```
tone: 'success' | 'warning' | 'error' | 'info' | 'neutral' | 'secondary'
variant: 'filled' | 'filled-soft' | 'outlined'
label: string      // always rendered — colour is never the only signal
icon?: ReactNode   // optional leading icon
```

**Blocked by:** 01 — Token-driven theme foundation (the chip reads semantic tokens).

**Status:** ready-for-agent

- [ ] The chip renders the provided label text in every case and applies the requested tone and variant to an observable role/attribute.
- [ ] An optional leading icon renders when provided and is absent otherwise.
- [ ] The component lives in the shared UI layer and imports no feature/domain code (respects the frontend seam — `shared` may depend only on `shared`).
- [ ] A Vitest + RTL test asserts the external behaviour above (visible text, tone/variant, icon), never internal class names.
- [ ] Gates green.
