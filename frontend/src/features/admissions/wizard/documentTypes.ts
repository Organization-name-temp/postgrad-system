/**
 * The document-type catalogue, mirrored from the backend DocumentType enum
 * (Modules\Admissions\Internal\Enums\DocumentType — Internal, so it has no
 * generated TS counterpart to import). These drive the per-type upload widgets and
 * the client-side `accept`/size hints only; the server re-validates every upload
 * against the enum, so it stays the single authority. Mirrors the same convention
 * as fieldOptions.ts.
 */
export type DocumentRequirement = 'required' | 'conditional' | 'optional'

export interface DocumentTypeConfig {
  /** Matches the backend DocumentType value. */
  type: string
  requirement: DocumentRequirement
  /** Largest single upload, in megabytes (matches the enum's maxSizeMb). */
  maxSizeMb: number
  /** How many of this type the application may hold (matches the enum's maxCount). */
  maxCount: number
  /** The `accept` attribute for the file input — the MIME types the type allows. */
  accept: string
  /** A short, human-readable list of accepted formats for the hint text. */
  formats: string
}

// The accepted-format sets, matching DocumentType::allowedMimes().
const SCAN = 'application/pdf,image/jpeg,image/png'
const IMAGE = 'image/jpeg,image/png'
const PDF = 'application/pdf'

const SCAN_FORMATS = 'PDF, JPG, PNG'
const IMAGE_FORMATS = 'JPG, PNG'
const PDF_FORMATS = 'PDF'

/**
 * Every document type, in the order the wizard presents them: the four required
 * documents first, then the conditional ones, then the optional extras.
 */
export const DOCUMENT_TYPES: readonly DocumentTypeConfig[] = [
  {
    type: 'graduation_certificate',
    requirement: 'required',
    maxSizeMb: 10,
    maxCount: 1,
    accept: SCAN,
    formats: SCAN_FORMATS,
  },
  {
    type: 'transcript',
    requirement: 'required',
    maxSizeMb: 10,
    maxCount: 1,
    accept: SCAN,
    formats: SCAN_FORMATS,
  },
  {
    type: 'national_id',
    requirement: 'required',
    maxSizeMb: 5,
    maxCount: 1,
    accept: SCAN,
    formats: SCAN_FORMATS,
  },
  {
    type: 'photo',
    requirement: 'required',
    maxSizeMb: 5,
    maxCount: 1,
    accept: IMAGE,
    formats: IMAGE_FORMATS,
  },
  {
    type: 'english_certificate',
    requirement: 'conditional',
    maxSizeMb: 10,
    maxCount: 1,
    accept: SCAN,
    formats: SCAN_FORMATS,
  },
  {
    type: 'msc_thesis',
    requirement: 'conditional',
    maxSizeMb: 20,
    maxCount: 1,
    accept: PDF,
    formats: PDF_FORMATS,
  },
  {
    type: 'cv',
    requirement: 'optional',
    maxSizeMb: 10,
    maxCount: 1,
    accept: PDF,
    formats: PDF_FORMATS,
  },
  {
    type: 'recommendation_letter',
    requirement: 'optional',
    maxSizeMb: 10,
    maxCount: 2,
    accept: SCAN,
    formats: SCAN_FORMATS,
  },
  {
    type: 'additional_certificate',
    requirement: 'optional',
    maxSizeMb: 10,
    maxCount: 5,
    accept: SCAN,
    formats: SCAN_FORMATS,
  },
] as const
