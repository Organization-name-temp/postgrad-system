import { pickLocaleName } from '../../../shared/i18n/localeName'
import type {
  TheCatalogBrowseTree200Data as CatalogTreeData,
  TheCatalogBrowseTree200DataFacultiesItem as FacultyItem,
  TheCatalogBrowseTree200DataFacultiesItemDepartmentsItem as DepartmentItem,
  TheCatalogBrowseTree200DataFacultiesItemDepartmentsItemProgramsItem as ProgramItem,
} from '../../../shared/api/generated/endpoints'

/**
 * The applicant's program picker reuses the Phase-1 catalog tree (ADR-0012) but
 * needs only its top three levels — Faculty → Department → Program — since an
 * applicant chooses a Program, not a Course. This module turns the shared browse
 * payload into that pruned, selectable tree, keeping the logic pure (and i18n-
 * agnostic, via injected label resolvers) so it is unit-tested directly while the
 * `ProgramStep` view stays thin — the same split as the catalog `treeModel`.
 */

/** A selectable Program leaf — the opaque id the applicant stores plus display bits. */
export interface ProgramLeaf {
  id: number
  code: string
  degreeLevel: string
  label: string
}

export type ProgramNodeKind = 'faculty' | 'department' | 'program'

/** A node in the pruned picker tree; `program` is set only on Program leaves. */
export interface ProgramNode {
  id: string
  kind: ProgramNodeKind
  label: string
  program?: ProgramLeaf
  children: ProgramNode[]
}

/** Label resolvers the view injects so this module stays i18n-agnostic and pure. */
export interface ProgramTreeLabels {
  lang: string
  /** e.g. "MSc in Computer Engineering · MSc". */
  program: (name: string, degreeLevel: string) => string
}

function programLeaf(program: ProgramItem, labels: ProgramTreeLabels): ProgramLeaf {
  const name = pickLocaleName(program, labels.lang, program.code ?? '')
  const degreeLevel = program.degree_level ?? ''

  return {
    id: program.id ?? 0,
    code: program.code ?? '',
    degreeLevel,
    label: labels.program(name, degreeLevel),
  }
}

function departmentNode(department: DepartmentItem, labels: ProgramTreeLabels): ProgramNode | null {
  // Only active programs are offerable; deactivation does not cascade (BR-18), so a
  // department may still carry an active program even if it is itself retired.
  const programs = (department.programs ?? []).filter((program) => program.is_active ?? false)
  if (programs.length === 0) {
    return null
  }

  return {
    id: `dept-${department.id ?? 0}`,
    kind: 'department',
    label: pickLocaleName(department, labels.lang, department.code ?? ''),
    children: programs.map((program) => {
      const leaf = programLeaf(program, labels)

      return {
        id: `program-${leaf.id}`,
        kind: 'program',
        label: leaf.label,
        program: leaf,
        children: [],
      }
    }),
  }
}

function facultyNode(faculty: FacultyItem, labels: ProgramTreeLabels): ProgramNode | null {
  const departments = (faculty.departments ?? [])
    .map((department) => departmentNode(department, labels))
    .filter((node): node is ProgramNode => node !== null)
  if (departments.length === 0) {
    return null
  }

  return {
    id: `faculty-${faculty.id ?? 0}`,
    kind: 'faculty',
    label: pickLocaleName(faculty, labels.lang, faculty.code ?? ''),
    children: departments,
  }
}

/**
 * Build the pruned Faculty → Department → Program picker tree from the catalog
 * browse payload, keeping only offerable (active) Programs and dropping any branch
 * left with none. Unlike the catalog browse tree there is no synthetic "University"
 * root — the picker lists Faculties directly.
 */
export function buildProgramTree(
  data: CatalogTreeData | undefined,
  labels: ProgramTreeLabels,
): ProgramNode[] {
  return (data?.faculties ?? [])
    .map((faculty) => facultyNode(faculty, labels))
    .filter((node): node is ProgramNode => node !== null)
}

/**
 * Flatten the picker tree to a lookup of Program leaves by id — so a selected id
 * (primary or alternative) can be resolved back to its label for the selection
 * summary without walking the tree each time.
 */
export function programsById(nodes: ProgramNode[]): Map<number, ProgramLeaf> {
  const map = new Map<number, ProgramLeaf>()
  const walk = (list: ProgramNode[]): void => {
    for (const node of list) {
      if (node.program) {
        map.set(node.program.id, node.program)
      }
      walk(node.children)
    }
  }
  walk(nodes)

  return map
}
