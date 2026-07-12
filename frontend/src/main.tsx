import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import i18n from './shared/i18n/config'
import { preloadHotFonts } from './app/theme/fonts'
import { App } from './app/App'

// Self-hosted fonts: importing fonts.ts injects the @font-face CSS; preload the
// active language's hot weights now and again whenever the language changes.
preloadHotFonts(i18n.language)
i18n.on('languageChanged', preloadHotFonts)

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
