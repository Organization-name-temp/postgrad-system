# 02 — Assign semantic Preparatory Requirement Roles

**What to build:** Let Catalog administrators assign the meaning of every Preparatory Curriculum Entry so automatic Exemptions and progress calculations target stable academic Roles rather than seed-specific Course codes.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] A Preparatory Curriculum Entry may hold exactly one of English Language, Arabic Language, Japanese Language, Japanese Culture, Key Skills, or Departmental Requirement.
- [ ] A complete Program Curriculum Version requires exactly one Entry for each of the six Roles, including one of its departmental Entries as Key Skills.
- [ ] Non-Preparatory Entries cannot receive a Preparatory Requirement Role, and duplicates or missing Roles produce bilingual validation errors.
- [ ] Catalog Public DTOs expose the Role with the Entry while preserving module boundaries and opaque IDs.
- [ ] Existing Curriculum administration supports assigning and reviewing Roles in English and Arabic with correct RTL behavior.
- [ ] Seeded Curricula are migrated to the semantic Roles without changing their intended requirements.
- [ ] API, UI, completeness validation, OpenAPI generation, and boundary tests cover all six Roles.
