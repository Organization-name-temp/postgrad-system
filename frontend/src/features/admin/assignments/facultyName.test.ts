import { describe, expect, it } from 'vitest'
import { facultyName } from './facultyName'

const faculties = [
  { id: 1, name_en: 'Faculty of Engineering', name_ar: 'كلية الهندسة' },
  { id: 2, name_en: 'Faculty of Science', name_ar: 'كلية العلوم' },
]

describe('facultyName', () => {
  it('resolves an opaque faculty id to its English name', () => {
    expect(facultyName(faculties, 1, 'en', 'Faculty #1')).toBe('Faculty of Engineering')
  })

  it('resolves to the Arabic name in Arabic', () => {
    expect(facultyName(faculties, 2, 'ar', 'كلية رقم ٢')).toBe('كلية العلوم')
  })

  it('falls back when the faculty is not in the list (dropped or not loaded)', () => {
    expect(facultyName(faculties, 99, 'en', 'Faculty #99')).toBe('Faculty #99')
  })

  it('falls back when the id is undefined', () => {
    expect(facultyName(faculties, undefined, 'en', 'Unknown')).toBe('Unknown')
  })
})
