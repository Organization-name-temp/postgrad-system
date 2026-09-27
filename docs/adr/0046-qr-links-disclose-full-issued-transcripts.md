# QR verification links expose the full issued Transcript

**Status:** accepted — scoped exception to ADR-0016

An official Transcript's QR code links to a public view of the full immutable issued
Transcript, including its Student identity, academic rows, and summaries. The recipient
does not need a PRS account. The view shows the issued snapshot rather than the Student's
latest academic record.

The approved Student identity fields include University ID, Identification Type, and
National ID/passport number. That identifier is deliberately visible in the full public
verification view while the issuance is valid. Payment and refund evidence are not
Transcript content and are not exposed by the QR link.

The chosen purpose is to let recipients inspect the complete postgraduate Transcript,
rather than receive only a reference and validity response. Anyone holding the link can
read the issuance; an unguessable token prevents enumeration but does not authenticate
the recipient. The token identifies one issuance, never general access to the Student's
records or other issuances.

This deliberately permits a capability link for official Transcript verification despite
ADR-0016's rejection of that pattern for private document downloads. Unofficial
Transcripts and unrelated private files retain authenticated authorization. Public
verification discloses Transcript content only, not underlying evidence attachments,
review notes, or security Audit Logs.

For valid issuances this disclosure applies even after later academic changes. Explicit
revocation switches the public page to reference and Revoked status only, under
[ADR-0047](0047-official-transcript-revocation-preserves-history.md).

Verification links have no automatic expiry. Graduation, termination, account changes,
and newer issuances do not invalidate them; only explicit Student Affairs revocation
changes their disclosure to the revoked response.
