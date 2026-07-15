# 07 — Academic score validation (GPA + English)

**What to build:** GPA and English-test scores can no longer be nonsensical. The applicant chooses their **GPA scale first**, then enters a GPA that must fall within that scale; and the **English score** must fall within the range of the test they selected (an IELTS of 250 is rejected). Cross-field rules on client (Zod) + server.

**Blocked by:** 05 — React Hook Form + Zod foundation.

**Status:** ready-for-agent

- [ ] The GPA-scale Select precedes the GPA/CGPA fields, which stay disabled until a scale is chosen; each value is bounded `0 ≤ v ≤ selectedScaleMax`.
- [ ] The English score is validated against the selected test's official range; `None` keeps the existing "no score" presence rule.
- [ ] Both rules enforced client + server; violations produce inline `422`s.
- [ ] Tests include a unit test of the per-test English-score range map and the GPA-vs-scale rule; GPA above the chosen scale ceiling → `422`; English score outside the test's range → `422`. Gates green.
