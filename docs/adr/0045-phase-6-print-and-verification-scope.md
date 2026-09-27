# Phase 6 requires printable transcripts and verification; PDF and signing are deferred

**Status:** accepted — amends SRS FR-TRN-001 and BR-64 for the localhost build

Phase 6 requires bilingual on-screen and browser-printable Transcripts, immutable official
issuances, a university seal/watermark, and QR verification. Server-generated PDF export
and cryptographic digital signatures are explicitly deferred rather than optional
completion criteria. Issuer attribution records who certified an issuance but is not
described as a digital signature.

This keeps the document-issuance and verification workflow in the learning build without
making completion depend on headless-browser PDF infrastructure or signing credentials.
The alternative was to require the full production document format from SRS §4.9 now.
The seal/watermark is presentation, not a substitute for cryptographic authenticity.

Verification disclosure is resolved in ADR-0046/0047: possession of an unguessable
per-issuance QR link permits public access to the full valid issued Transcript; revoked
links reveal reference/status only. This is separate from authenticated browse access.
