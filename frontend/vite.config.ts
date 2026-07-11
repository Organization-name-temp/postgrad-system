/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// The SPA and API share one origin in dev: the browser talks only to Vite
// (5173), which proxies /api and /sanctum to Laravel (8000). Same-origin means
// Sanctum's cookie/CSRF session auth works without cross-origin config (Q10).
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/sanctum': { target: 'http://127.0.0.1:8000', changeOrigin: true },
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/test/setup.ts',
    // e2e/ is Playwright's; keep it out of the Vitest run.
    exclude: ['**/node_modules/**', '**/e2e/**'],
  },
})
