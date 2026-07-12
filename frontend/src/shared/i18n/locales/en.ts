// English resources. Namespaces: `common` (cross-cutting chrome) and `auth`
// (the auth feature's strings). Later features add their own namespaces.
export const en = {
  common: {
    appName: 'Postgraduate Registration System',
    logOut: 'Log out',
    loading: 'Loading…',
    apiStatus: 'API status: {{status}}',
    language: {
      label: 'Language',
      en: 'English',
      ar: 'العربية',
    },
    theme: {
      toggle: 'Toggle light and dark theme',
      light: 'Light',
      dark: 'Dark',
      system: 'System',
    },
    nav: {
      label: 'Main navigation',
      open: 'Open navigation menu',
      skipToContent: 'Skip to main content',
      dashboard: 'Dashboard',
    },
    menu: {
      account: 'Account menu',
    },
  },
  auth: {
    login: {
      title: 'Sign in',
      submit: 'Sign in',
      submitting: 'Signing in…',
      error: 'Unable to sign in. Please try again.',
      email: 'Email',
      password: 'Password',
    },
    dashboard: {
      title: 'Dashboard',
      roles: 'Roles',
      permissions: 'Permissions',
    },
    catalog: {
      title: 'Catalog management',
      error: 'Could not load the gated resource.',
    },
  },
} as const
