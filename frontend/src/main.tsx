import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './shared/i18n/config'
import { App } from './app/App'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
