# Preparatory exemption rules target semantic requirement roles

**Status:** accepted — extends the Curriculum Entry model in
[ADR-0010](0010-catalog-curriculum-model.md)

Each Program requires six Preparatory Courses: four university-wide and two departmental.
The E-JUST BSc exemption names Japanese Language, Japanese Culture, and **Key Skills**, but
the current catalog has no universal Key Skills Course and the two departmental Courses
vary by Program. Hard-coding one Course id or changing the four-plus-two composition would
make an institution-wide qualification rule depend on today's seed data.

**Decision: every Preparatory Curriculum Entry carries a semantic Preparatory Requirement
Role, and one of each Program's two departmental entries holds the `Key Skills` role.**
Automatic Exemption rules target these roles rather than Course codes or ids. The six roles
are English Language, Arabic Language, Japanese Language, Japanese Culture, Key Skills, and
Departmental Requirement. A Program has exactly one Preparatory Entry in each role.

## Considered options

- **Role on the Curriculum Entry (chosen).** The meaning is per Program, matching the
  existing per-Program Curriculum model while preserving four shared plus two departmental
  Courses.
- **A shared PRE405 Key Skills Course (rejected).** This changes the academic composition to
  five university-wide plus one departmental requirement.
- **Alias an existing PRE401–PRE404 Course (rejected).** This conflates two named academic
  requirements and makes the E-JUST BSc exemption undercount or exempt the wrong Course.

## Consequences

- Catalog validates that each Program's Preparatory Curriculum contains exactly one Entry
  for each of the six roles before the Curriculum is complete.
- Exemption evaluation resolves roles through `Catalog\Public` and records the actual
  target Course id on each Exemption.
- Course codes and names may change without changing qualification rules; historical
  Exemptions keep their course target snapshot.
