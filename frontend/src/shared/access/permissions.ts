/**
 * Permission keys mirrored from the backend `Permission` enum
 * (Modules\Identity\Public\Enums\Permission). The SPA compares these keys for
 * UX gating only — the API re-checks every request and remains the sole
 * authority (ADR-0008). Lives in `shared` so every feature can gate on it
 * without reaching into another feature (eslint-plugin-boundaries). Only the
 * keys the UI actually gates on live here.
 */
export const PERMISSION = {
  viewCatalog: 'catalog.view',
  manageCatalog: 'catalog.manage',
} as const
