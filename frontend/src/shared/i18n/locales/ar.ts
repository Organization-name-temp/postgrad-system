// Arabic resources (RTL). Mirrors the English namespace structure.
export const ar = {
  common: {
    appName: 'نظام تسجيل الدراسات العليا',
    logOut: 'تسجيل الخروج',
    loading: 'جارٍ التحميل…',
    apiStatus: 'حالة الواجهة البرمجية: {{status}}',
    language: {
      label: 'اللغة',
      en: 'English',
      ar: 'العربية',
    },
    theme: {
      toggle: 'تبديل السمة الفاتحة والداكنة',
      light: 'فاتح',
      dark: 'داكن',
      system: 'النظام',
    },
  },
  auth: {
    login: {
      title: 'تسجيل الدخول',
      submit: 'تسجيل الدخول',
      submitting: 'جارٍ تسجيل الدخول…',
      error: 'تعذّر تسجيل الدخول. يرجى المحاولة مرة أخرى.',
      email: 'البريد الإلكتروني',
      password: 'كلمة المرور',
    },
    dashboard: {
      title: 'لوحة التحكم',
      roles: 'الأدوار',
      permissions: 'الصلاحيات',
    },
    catalog: {
      title: 'إدارة المقررات',
      error: 'تعذّر تحميل المورد المحمي.',
    },
  },
} as const
