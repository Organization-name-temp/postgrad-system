import { defineConfig } from 'orval'

// Generates the typed API client (TanStack Query hooks + Zod schemas) from the
// backend's Scribe-produced OpenAPI spec (../backend/openapi.yaml). The output
// is committed and drift-checked in the gate; requests flow through our shared
// Axios instance (see src/shared/api/http.ts). Ticket 03.
export default defineConfig({
  prs: {
    input: '../backend/openapi.yaml',
    output: {
      mode: 'single',
      target: 'src/shared/api/generated/endpoints.ts',
      client: 'react-query',
      httpClient: 'axios',
      prettier: true,
      override: {
        mutator: { path: './src/shared/api/http.ts', name: 'customInstance' },
      },
    },
  },
  prsZod: {
    input: '../backend/openapi.yaml',
    output: {
      mode: 'single',
      target: 'src/shared/api/generated/zod.ts',
      client: 'zod',
      prettier: true,
    },
  },
})
