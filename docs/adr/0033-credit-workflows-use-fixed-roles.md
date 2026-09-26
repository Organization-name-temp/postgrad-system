# Credit-decision workflows use the fixed ten roles

**Status:** accepted — clarifies the council names in SRS §4.5–§4.6 against the fixed
RBAC model in [ADR-0008](0008-rbac-enums-static-matrix.md)

The requirements route Exemptions, Transfers, and Waivers through a Department Council
and, for Transfers, a Faculty Council. Neither is one of the system's ten Roles. Adding
both would expand a bylaws-derived role set; using the Dean as a faculty-council proxy
would contradict the existing permission matrix, which grants Transfer/Waiver approval to
the Advisor, Department Head, and Education & Academic Affairs Council.

**Decision: the workflows use the existing fixed Roles and explicit sequential stages.**
A manual Exemption moves Student submission → Advisor recommendation → Department Head
final decision. A Transfer or Waiver moves Student submission → Advisor recommendation →
Department Head academic verification → Education & Academic Affairs Council final
decision. A rejection at any review stage ends the request; only the final approval makes
the decision count in the Credit-Hour Summary.

The Department Head is the system actor accountable for the departmental review that the
requirements call “Department Council.” The existing Council Role is the final authority
for Transfer/Waiver requests; “Faculty Council” is not introduced as a second role or
parallel approval body.

## Considered options

- **Use the fixed Roles (chosen).** Matches the permission matrix and gives every stage a
  real actor. It normalizes two institutional body names rather than representing them
  literally.
- **Add Department Council and Faculty Council Roles (rejected).** Mirrors the prose
  literally, but expands the fixed RBAC vocabulary and multiplies permission, account,
  navigation, and test changes across the application.
- **Use Department Head and Dean as council proxies (rejected).** Looks institutionally
  hierarchical, but grants the Dean a power absent from §3.2 and leaves the existing
  Council approval permission without a purpose.

## Consequences

- Request status must distinguish the two recommendation/verification stages from the
  final decision; holding the broad approval Permission does not allow skipping stages.
- Scope remains actor-specific: Advisors act only for advisees, Department Heads only for
  their Departments, and Council members on requests that reached final review.
- Automatic Exemptions bypass this workflow because their accepted admission evidence is
  itself the configured decision basis.
