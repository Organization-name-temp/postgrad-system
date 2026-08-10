import { describe, expect, it } from 'vitest'
import { nameById } from './nameById'

const faculties = [
  { id: 1, name_en: 'Faculty of Engineering', name_ar: 'كلية الهندسة' },
  { id: 2, name_en: 'Faculty of Science', name_ar: 'كلية العلوم' },
]

describe('nameById', () => {
  it('resolves an opaque id to its English name', () => {
    expect(nameById(faculties, 1, 'en', 'Faculty #1')).toBe('Faculty of Engineering')
  })

  it('resolves to the Arabic name in Arabic', () => {
    expect(nameById(faculties, 2, 'ar', 'كلية رقم ٢')).toBe('كلية العلوم')
  })

  it('falls back when the entity is not in the list (dropped or not loaded)', () => {
    expect(nameById(faculties, 99, 'en', 'Faculty #99')).toBe('Faculty #99')
  })

  it('falls back when the id is undefined', () => {
    expect(nameById(faculties, undefined, 'en', 'Unknown')).toBe('Unknown')
  })
})
