/**
 * Permission keys mirrored from the backend `Permission` enum
 * (Modules\Identity\Public\Enums\Permission). The SPA compares these keys for
 * UX gating only — the API re-checks every request and remains the sole
 * authority (ADR-0008). Only the keys the UI actually gates on live here.
 */
export const PERMISSION = {
  manageCatalog: 'catalog.manage',
} as const
