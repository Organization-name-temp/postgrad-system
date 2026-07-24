import { describe, expect, it } from 'vitest'
import { groupByClassification } from './classification'
import type { EligibleCourse } from './classification'

/** A minimal eligible line carrying just the fields the grouping reads. */
function line(offeringId: number, classification: string): EligibleCourse {
  return { offering_id: offeringId, classification }
}

describe('groupByClassification', () => {
  it('groups courses in the fixed presentation order, foundation to research', () => {
    // Deliberately shuffled input — the output order must follow CLASSIFICATION_ORDER.
    const groups = groupByClassification([
      line(1, 'ResearchCourse'),
      line(2, 'Preparatory'),
      line(3, 'ExternalElective'),
      line(4, 'Core'),
      line(5, 'InternalElective'),
    ])

    expect(groups.map((g) => g.classification)).toEqual([
      'Preparatory',
      'Core',
      'InternalElective',
      'ExternalElective',
      'ResearchCourse',
    ])
  })

  it('keeps all courses of a classification together', () => {
    const groups = groupByClassification([line(1, 'Core'), line(2, 'Preparatory'), line(3, 'Core')])

    const core = groups.find((g) => g.classification === 'Core')
    expect(core?.courses.map((c) => c.offering_id)).toEqual([1, 3])
  })

  it('drops classifications with no courses', () => {
    const groups = groupByClassification([line(1, 'Preparatory')])

    expect(groups).toHaveLength(1)
    expect(groups[0].classification).toBe('Preparatory')
  })

  it('skips an unknown classification the server might add rather than rendering it unheaded', () => {
    const groups = groupByClassification([line(1, 'Core'), line(2, 'SomethingNew')])

    expect(groups).toHaveLength(1)
    expect(groups[0].classification).toBe('Core')
  })

  it('returns nothing for an empty list', () => {
    expect(groupByClassification([])).toEqual([])
  })
})
