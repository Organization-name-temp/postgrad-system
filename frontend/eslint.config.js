import js from '@eslint/js'
import tseslint from 'typescript-eslint'
import boundaries from 'eslint-plugin-boundaries'
import prettier from 'eslint-config-prettier'

// Feature-module seams — the frontend analogue of the backend's deptrac rules
// (architecture.md §7): features may not import each other, only `shared` (and
// their own files); `app` may compose anything. Enforced by
// eslint-plugin-boundaries.
export default tseslint.config(
  { ignores: ['dist', 'coverage', 'e2e', 'src/shared/api/generated', '**/*.config.*'] },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ['src/**/*.{ts,tsx}'],
    plugins: { boundaries },
    settings: {
      'boundaries/elements': [
        { type: 'app', pattern: 'src/app/**' },
        { type: 'feature', pattern: 'src/features/*/**', capture: ['feature'] },
        { type: 'shared', pattern: 'src/shared/**' },
      ],
    },
    rules: {
      'boundaries/dependencies': [
        'error',
        {
          default: 'disallow',
          policies: [
            // The app shell may compose anything.
            {
              from: { element: { types: 'app' } },
              allow: { to: { element: { types: { anyOf: ['app', 'feature', 'shared'] } } } },
            },
            // A feature may use shared; it may NOT reach into another feature.
            // (The own-feature-only allowance via capture is added + verified in
            // ticket 04, when the first real feature exists to test against.)
            {
              from: { element: { types: 'feature' } },
              allow: { to: { element: { types: 'shared' } } },
            },
            // Shared is cross-cutting and may depend only on shared.
            {
              from: { element: { types: 'shared' } },
              allow: { to: { element: { types: 'shared' } } },
            },
          ],
        },
      ],
    },
  },
  prettier,
)
