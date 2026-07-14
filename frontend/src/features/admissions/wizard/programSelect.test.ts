import { describe, expect, it } from 'vitest'
import { buildProgramTree, programsById } from './programSelect'
import type { TheCatalogBrowseTree200Data } from '../../../shared/api/generated/endpoints'

/*
| The program picker's non-trivial logic is pruning the catalog browse tree down to
| its selectable Programs. These pin that: only active Programs survive, empty
| branches are dropped, and the leaf carries the opaque id the applicant stores.
*/

const labels = {
  lang: 'en',
  program: (name: string, degree: string) => `${name} · ${degree}`,
}

const tree: TheCatalogBrowseTree200Data = {
  faculties: [
    {
      id: 1,
      code: 'FoE',
      name_en: 'Faculty of Engineering',
      name_ar: 'كلية الهندسة',
      is_active: true,
      departments: [
        {
          id: 1,
          code: 'CE',
          name_en: 'Computer Engineering',
          name_ar: 'هندسة الحاسوب',
          is_active: true,
          programs: [
            {
              id: 10,
              code: 'MSC-CE',
              name_en: 'MSc in Computer Engineering',
              name_ar: 'ماجستير هندسة الحاسوب',
              degree_level: 'MSc',
              is_active: true,
              categories: [],
            },
            {
              id: 11,
              code: 'PHD-CE',
              name_en: 'PhD in Computer Engineering',
              name_ar: 'دكتوراه هندسة الحاسوب',
              degree_level: 'PhD',
              is_active: false, // retired — must not be offerable
              categories: [],
            },
          ],
        },
        {
          // A department with no active programs is pruned entirely.
          id: 2,
          code: 'EE',
          name_en: 'Electrical Engineering',
          name_ar: 'الهندسة الكهربية',
          is_active: true,
          programs: [
            {
              id: 20,
              code: 'MSC-EE',
              name_en: 'MSc in Electrical Engineering',
              name_ar: 'ماجستير الهندسة الكهربية',
              degree_level: 'MSc',
              is_active: false,
              categories: [],
            },
          ],
        },
      ],
    },
    {
      // A faculty with no offerable programs at all is pruned.
      id: 2,
      code: 'BAS',
      name_en: 'Basic Sciences',
      name_ar: 'العلوم الأساسية',
      is_active: true,
      departments: [],
    },
  ],
}

describe('buildProgramTree', () => {
  it('keeps only active programs and prunes branches left empty', () => {
    const nodes = buildProgramTree(tree, labels)

    // Only FoE survives; BAS (no programs) and the EE department (all inactive) are gone.
    expect(nodes).toHaveLength(1)
    expect(nodes[0].kind).toBe('faculty')
    expect(nodes[0].label).toBe('Faculty of Engineering')
    expect(nodes[0].children).toHaveLength(1)

    const department = nodes[0].children[0]
    expect(department.kind).toBe('department')
    expect(department.children).toHaveLength(1) // the active MSc, not the retired PhD
  })

  it('carries the opaque id, code, degree level and a combined label on the leaf', () => {
    const leaf = buildProgramTree(tree, labels)[0].children[0].children[0]

    expect(leaf.kind).toBe('program')
    expect(leaf.program).toEqual({
      id: 10,
      code: 'MSC-CE',
      degreeLevel: 'MSc',
      label: 'MSc in Computer Engineering · MSc',
    })
  })

  it('renders names in the active locale', () => {
    const nodes = buildProgramTree(tree, { ...labels, lang: 'ar' })

    expect(nodes[0].label).toBe('كلية الهندسة')
    expect(nodes[0].children[0].label).toBe('هندسة الحاسوب')
  })

  it('returns an empty list for an absent payload', () => {
    expect(buildProgramTree(undefined, labels)).toEqual([])
  })
})

describe('programsById', () => {
  it('flattens the tree to a lookup of selectable programs by id', () => {
    const map = programsById(buildProgramTree(tree, labels))

    expect([...map.keys()]).toEqual([10])
    expect(map.get(10)?.code).toBe('MSC-CE')
  })
})
