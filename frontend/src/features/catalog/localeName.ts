// The locale-name picker now lives in `shared/i18n` (every bilingual feature
// needs it, not just catalog). Re-exported here so catalog's existing imports
// keep working through the feature-relative path.
export { pickLocaleName } from '../../shared/i18n/localeName'
