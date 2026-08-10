/**
 * Coerces a Sheet line's `grade` or `incomplete` field, as read off the generated API client,
 * back to its real nullable-object shape.
 *
 * The real payload is always either `null` or a populated object — Grading's
 * `BuildsSheetPayload::lineArrays()` guarantees it — but `grade` and `incomplete` on one line
 * are mutually exclusive (one populated, the other `null`), and Scribe/Orval infer an array
 * field's type from a single representative example. Whichever field that example shows as
 * `null` gets typed as a bare nullable scalar rather than a nullable object, so the generated
 * type for one of the two fields is imprecise on these endpoints. This is a deliberate, narrow
 * escape hatch at the seam where a page maps the generated response onto its own
 * `GradeSheetLine[]` — never spread further into the codebase.
 */
export function coerceNullableObject<T>(value: unknown): T | null {
  return (value ?? null) as T | null
}
