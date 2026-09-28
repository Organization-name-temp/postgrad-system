# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: course-collections.spec.ts >> Course filters and authenticated export retain their URL in ar
- Location: e2e/course-collections.spec.ts:9:3

# Error details

```
Test timeout of 60000ms exceeded.
```

```
Error: expect(locator).toHaveAttribute(expected) failed

Locator:  locator('html')
Expected: "ltr"
Received: ""

```

# Page snapshot

```yaml
- generic [ref=e3]:
  - link "Skip to main content" [ref=e4] [cursor=pointer]:
    - /url: "#main-content"
  - banner [ref=e5]:
    - generic [ref=e6]:
      - img "Postgraduate Registration System" [ref=e8]
      - generic [ref=e9]: Postgraduate Registration System
      - generic [ref=e10]:
        - group "Language" [ref=e11]:
          - button "English" [active] [pressed] [ref=e12] [cursor=pointer]: EN
          - button "العربية" [ref=e13] [cursor=pointer]: ع
        - button "Toggle light and dark theme" [ref=e14] [cursor=pointer]:
          - img [ref=e15]
        - button "Open notifications" [ref=e17] [cursor=pointer]:
          - img [ref=e19]
        - button "Account menu" [ref=e21] [cursor=pointer]:
          - img [ref=e22]
  - navigation "Main navigation" [ref=e27]:
    - list [ref=e28]:
      - listitem [ref=e29]:
        - link "Dashboard" [ref=e30] [cursor=pointer]:
          - /url: /
          - img [ref=e32]
          - generic [ref=e35]: Dashboard
    - list [ref=e36]:
      - listitem [ref=e37]: Analytics
      - listitem [ref=e38]:
        - link "Termination Report" [ref=e39] [cursor=pointer]:
          - /url: /analytics/termination
          - img [ref=e41]
          - generic [ref=e44]: Termination Report
      - listitem [ref=e45]:
        - link "Graduation Readiness Report" [ref=e46] [cursor=pointer]:
          - /url: /analytics/graduation-readiness
          - img [ref=e48]
          - generic [ref=e51]: Graduation Readiness Report
      - listitem [ref=e52]:
        - link "Academic Standing Report" [ref=e53] [cursor=pointer]:
          - /url: /analytics/academic-standing
          - img [ref=e55]
          - generic [ref=e58]: Academic Standing Report
      - listitem [ref=e59]:
        - link "Application Pipeline" [ref=e60] [cursor=pointer]:
          - /url: /analytics/application-pipeline
          - img [ref=e62]
          - generic [ref=e65]: Application Pipeline
    - list [ref=e66]:
      - listitem [ref=e67]: Admissions
      - listitem [ref=e68]:
        - link "Screening queue" [ref=e69] [cursor=pointer]:
          - /url: /screening
          - img [ref=e71]
          - generic [ref=e74]: Screening queue
      - listitem [ref=e75]:
        - link "Dean review" [ref=e76] [cursor=pointer]:
          - /url: /dean
          - img [ref=e78]
          - generic [ref=e81]: Dean review
      - listitem [ref=e82]:
        - link "Admission decisions" [ref=e83] [cursor=pointer]:
          - /url: /dean/decisions
          - img [ref=e85]
          - generic [ref=e88]: Admission decisions
      - listitem [ref=e89]:
        - link "Interview scheduling" [ref=e90] [cursor=pointer]:
          - /url: /interviews/scheduling
          - img [ref=e92]
          - generic [ref=e96]: Interview scheduling
      - listitem [ref=e97]:
        - link "Interview slots" [ref=e98] [cursor=pointer]:
          - /url: /interviews/slots
          - img [ref=e100]
          - generic [ref=e103]: Interview slots
    - list [ref=e104]:
      - listitem [ref=e105]: Catalog
      - listitem [ref=e106]:
        - link "Browse catalog" [ref=e107] [cursor=pointer]:
          - /url: /catalog
          - img [ref=e109]
          - generic [ref=e112]: Browse catalog
      - listitem [ref=e113]:
        - link "Faculties" [ref=e114] [cursor=pointer]:
          - /url: /catalog/faculties
          - img [ref=e116]
          - generic [ref=e119]: Faculties
      - listitem [ref=e120]:
        - link "Departments" [ref=e121] [cursor=pointer]:
          - /url: /catalog/departments
          - img [ref=e123]
          - generic [ref=e126]: Departments
      - listitem [ref=e127]:
        - link "Programs" [ref=e128] [cursor=pointer]:
          - /url: /catalog/programs
          - img [ref=e130]
          - generic [ref=e133]: Programs
      - listitem [ref=e134]:
        - link "Courses" [ref=e135] [cursor=pointer]:
          - /url: /catalog/courses
          - img [ref=e137]
          - generic [ref=e141]: Courses
    - list [ref=e142]:
      - listitem [ref=e143]: Calendar
      - listitem [ref=e144]:
        - link "Admission cycles" [ref=e145] [cursor=pointer]:
          - /url: /admin/cycles
          - img [ref=e147]
          - generic [ref=e150]: Admission cycles
      - listitem [ref=e151]:
        - link "Semesters" [ref=e152] [cursor=pointer]:
          - /url: /admin/semesters
          - img [ref=e154]
          - generic [ref=e157]: Semesters
    - list [ref=e158]:
      - listitem [ref=e159]: Grading
      - listitem [ref=e160]:
        - link "Student grades" [ref=e161] [cursor=pointer]:
          - /url: /grading/students
          - img [ref=e163]
          - generic [ref=e166]: Student grades
  - main [ref=e167]:
    - generic [ref=e169]:
      - navigation [ref=e170]:
        - list [ref=e171]:
          - listitem [ref=e172]:
            - link "Courses" [ref=e173] [cursor=pointer]:
              - /url: /catalog/courses
          - listitem [ref=e174]: /
          - listitem [ref=e175]:
            - paragraph [ref=e176]: CSE511
      - generic [ref=e177]:
        - generic [ref=e178]:
          - heading "Advanced Algorithms" [level=1] [ref=e179]
          - paragraph [ref=e180]: خوارزميات متقدمة
          - paragraph [ref=e181]: CSE511 · Level 500 · 3 / 0 / 0 · 3 CH · Computer Engineering
        - button "Edit details" [ref=e182] [cursor=pointer]:
          - img [ref=e184]
          - text: Edit details
      - separator [ref=e186]
      - heading "Appears in" [level=2] [ref=e187]
      - list [ref=e188]:
        - listitem [ref=e189]:
          - generic [ref=e190]:
            - generic [ref=e191]: MSc in Computer Engineering
            - paragraph [ref=e192]: Program Core
      - separator [ref=e193]
      - generic [ref=e194]:
        - heading "Version history" [level=2] [ref=e195]:
          - img [ref=e196]
          - text: Version history
        - paragraph [ref=e198]: No versions yet.
```

# Test source

```ts
  1  | import { expect, type Page } from '@playwright/test'
  2  |
  3  | export async function selectAuthenticatedLanguage(page: Page, locale: 'en' | 'ar') {
  4  |   if ((await page.locator('html').getAttribute('lang')) !== locale) {
  5  |     const persisted = page.waitForResponse(async (response) => {
  6  |       if (
  7  |         new URL(response.url()).pathname !== '/api/v1/me' ||
  8  |         response.request().method() !== 'GET' ||
  9  |         response.status() !== 200
  10 |       )
  11 |         return false
  12 |       return (await response.json()).data.preferred_locale === locale
  13 |     })
  14 |     await page
  15 |       .getByRole('button', { name: locale === 'ar' ? 'العربية' : 'English', exact: true })
  16 |       .click()
  17 |     await persisted
  18 |   }
  19 |   await expect(page.locator('html')).toHaveAttribute('lang', locale)
> 20 |   await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr')
     |                                      ^ Error: expect(locator).toHaveAttribute(expected) failed
  21 | }
  22 |
  23 | export async function loginInEnglish(page: Page, email: string) {
  24 |   await page.goto('/login')
  25 |   await page.getByLabel('Email').fill(email)
  26 |   await page.getByLabel('Password').fill('password')
  27 |   const currentUser = page.waitForResponse(
  28 |     (response) =>
  29 |       new URL(response.url()).pathname === '/api/v1/me' &&
  30 |       response.request().method() === 'GET' &&
  31 |       response.status() === 200,
  32 |   )
  33 |   await page.getByRole('button', { name: 'Sign in' }).click()
  34 |   const user = (await (await currentUser).json()).data
  35 |   expect(user.email).toBe(email)
  36 |   await expect(page).toHaveURL(/\/$/)
  37 |   await expect(page.locator('html')).toHaveAttribute('lang', user.preferred_locale)
  38 |   await selectAuthenticatedLanguage(page, 'en')
  39 | }
  40 |
```
