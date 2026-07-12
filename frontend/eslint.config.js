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
  // "Never hard-code a colour" (design-system.md §0): ban raw hex-colour literals
  // in app/feature/shared code. Exempt `tokens.ts` (the authoritative ramps) and
  // test files (WebAIM reference values / colour assertions). Two selectors catch
  // standalone hex (`color: '#E4002B'`) and hex embedded in a value
  // (`'1px solid #E4002B'`). Other colour forms — template literals, rgb()/hsl(),
  // CSS named colours — are left to code review. Enforces the Phase 0.5 re-skin.
  {
    files: ['src/**/*.{ts,tsx}'],
    ignores: ['src/app/theme/tokens.ts', '**/*.test.{ts,tsx}'],
    rules: {
      'no-restricted-syntax': [
        'error',
        {
          // Standalone #RGB / #RGBA / #RRGGBBAA. (6-digit is handled below, so a
          // standalone #RRGGBB isn't flagged twice.)
          selector: 'Literal[value=/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{8})$/]',
          message:
            'Use a design token (theme.palette / theme.vars / tokens.ts), not a raw hex colour — design-system.md §0.',
        },
        {
          // 6-digit hex anywhere (standalone or embedded in a border/shadow shorthand).
          selector: 'Literal[value=/#[0-9a-fA-F]{6}\\b/]',
          message:
            'Use a design token (theme.palette / theme.vars / tokens.ts), not a raw hex colour — design-system.md §0.',
        },
      ],
    },
  },
  prettier,
)
