import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import { en } from './locales/en'
import { ar } from './locales/ar'
import { http } from '../api/http'
import { directionFor } from './direction'

export const SUPPORTED_LOCALES = ['en', 'ar'] as const
export type Locale = (typeof SUPPORTED_LOCALES)[number]

void i18n.use(initReactI18next).init({
  resources: { en, ar },
  lng: 'en',
  fallbackLng: 'en',
  supportedLngs: SUPPORTED_LOCALES,
  ns: [
    'common',
    'auth',
    'catalog',
    'admin',
    'notifications',
    'admissions',
    'enrollment',
    'grading',
  ],
  defaultNS: 'common',
  interpolation: { escapeValue: false }, // React already escapes.
})

/**
 * Keep the document direction, `<html lang>`, and the API `Accept-Language`
 * header aligned with the active language — the full loop the phase proves:
 * a language change flips the UI to RTL/LTR and localizes server messages.
 */
function applyLocale(lng: string): void {
  const dir = directionFor(lng)
  document.documentElement.setAttribute('lang', lng)
  document.documentElement.setAttribute('dir', dir)
  http.defaults.headers.common['Accept-Language'] = lng
}

applyLocale(i18n.language)
i18n.on('languageChanged', applyLocale)

export default i18n
