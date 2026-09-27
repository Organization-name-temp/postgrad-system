/// <reference types="vitest/config" />
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

// The SPA and API share one origin in dev: the browser talks only to Vite
// (5173), which proxies /api and /sanctum to Laravel (8000). Same-origin means
// Sanctum's cookie/CSRF session auth works without cross-origin config (Q10).
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const devPort = Number(env.VITE_DEV_PORT || 5173)
  const apiTarget = env.VITE_API_TARGET || 'http://127.0.0.1:8000'

  return {
    plugins: [react()],
    server: {
      port: devPort,
      // Fail loudly if the selected port is taken instead of silently drifting
      // to an origin that Sanctum does not consider stateful.
      strictPort: true,
      proxy: {
        '/api': { target: apiTarget, changeOrigin: true },
        '/sanctum': { target: apiTarget, changeOrigin: true },
      },
    },
    test: {
      environment: 'jsdom',
      globals: true,
      setupFiles: './src/test/setup.ts',
      // Bound MUI/jsdom worker memory and event-loop contention on shared hosts.
      // Unbounded CPU-based forks can miss real async UI responses before assertions.
      maxWorkers: 2,
      // MUI interaction tests can exceed Vitest's 5s default when the full
      // 80-file suite transforms and renders in parallel on CI-sized hosts.
      testTimeout: 20_000,
      // e2e/ is Playwright's; keep it out of the Vitest run.
      exclude: ['**/node_modules/**', '**/e2e/**'],
    },
  }
})
