import { pickLocaleName } from '../localeName'
import type {
  TheCatalogBrowseTree200Data as CatalogTreeData,
  TheCatalogBrowseTree200DataFacultiesItem as FacultyItem,
  TheCatalogBrowseTree200DataFacultiesItemDepartmentsItem as DepartmentItem,
  TheCatalogBrowseTree200DataFacultiesItemDepartmentsItemProgramsItem as ProgramItem,
  TheCatalogBrowseTree200DataFacultiesItemDepartmentsItemProgramsItemCategoriesItem as CategoryItem,
  TheCatalogBrowseTree200DataFacultiesItemDepartmentsItemProgramsItemCategoriesItemCoursesItem as CourseItem,
} from '../../../shared/api/generated/endpoints'

/** The synthetic root the SPA prepends — "University" is not a catalog entity. */
export const UNIVERSITY_ID = 'university'

export type TreeNodeKind =
  'university' | 'faculty' | 'department' | 'program' | 'category' | 'course'

/**
 * A display node in the browse tree. The API hierarchy (Faculty → Department →
 * Program → Category → Course) is flattened into this uniform shape so the view
 * renders it recursively and the filter walks it generically. `searchText` is the
 * lower-cased haystack the client-side filter matches; `courseId` is set only on
 * Course leaves so a click can open the Course detail.
 */
export interface TreeNode {
  id: string
  kind: TreeNodeKind
  label: string
  searchText: string
  /** Course leaves only — drives the active/inactive StatusChip. */
  isActive?: boolean
  /** Course leaves only — the id to open the Course detail with. */
  courseId?: number
  children: TreeNode[]
}

/** Label resolvers the view injects so this module stays i18n-agnostic and pure. */
export interface TreeLabels {
  lang: string
  /** The synthetic University root's label. */
  university: string
  /** e.g. "MSc in Computer Engineering · MSc". */
  program: (name: string, degreeLevel: string) => string
  /** e.g. "Program Core · 6 CH required". */
  category: (category: string, requiredCh: number) => string
}

function haystack(...parts: Array<string | undefined>): string {
  return parts
    .filter((part): part is string => Boolean(part))
    .join(' ')
    .toLowerCase()
}

function courseNode(
  course: CourseItem,
  programId: number,
  category: string,
  labels: TreeLabels,
): TreeNode {
  const name = pickLocaleName(course, labels.lang, course.code ?? '')
  return {
    id: `course-${programId}-${category}-${course.id}`,
    kind: 'course',
    label: `${course.code ?? ''} — ${name}`,
    searchText: haystack(course.code, course.name_en, course.name_ar),
    isActive: course.is_active ?? false,
    courseId: course.id,
    children: [],
  }
}

function categoryNode(category: CategoryItem, programId: number, labels: TreeLabels): TreeNode {
  const key = category.category ?? ''
  return {
    id: `cat-${programId}-${key}`,
    kind: 'category',
    label: labels.category(key, category.required_ch ?? 0),
    searchText: haystack(key),
    children: (category.courses ?? []).map((course) => courseNode(course, programId, key, labels)),
  }
}

function programNode(program: ProgramItem, labels: TreeLabels): TreeNode {
  const id = program.id ?? 0
  const name = pickLocaleName(program, labels.lang, program.code ?? '')
  return {
    id: `program-${id}`,
    kind: 'program',
    label: labels.program(name, program.degree_level ?? ''),
    searchText: haystack(program.code, program.name_en, program.name_ar, program.degree_level),
    children: (program.categories ?? []).map((category) => categoryNode(category, id, labels)),
  }
}

function departmentNode(department: DepartmentItem, labels: TreeLabels): TreeNode {
  return {
    id: `dept-${department.id ?? 0}`,
    kind: 'department',
    label: pickLocaleName(department, labels.lang, department.code ?? ''),
    searchText: haystack(department.code, department.name_en, department.name_ar),
    children: (department.programs ?? []).map((program) => programNode(program, labels)),
  }
}

function facultyNode(faculty: FacultyItem, labels: TreeLabels): TreeNode {
  return {
    id: `faculty-${faculty.id ?? 0}`,
    kind: 'faculty',
    label: pickLocaleName(faculty, labels.lang, faculty.code ?? ''),
    searchText: haystack(faculty.code, faculty.name_en, faculty.name_ar),
    children: (faculty.departments ?? []).map((department) => departmentNode(department, labels)),
  }
}

/**
 * Build the browse tree from the API payload, prepending the synthetic University
 * root (ADR-0010 — "University" is a tree root, not a table). Returns a single-item
 * list `[university]` so the view and filter treat the root uniformly.
 */
export function buildCatalogTree(
  data: CatalogTreeData | undefined,
  labels: TreeLabels,
): TreeNode[] {
  const faculties = (data?.faculties ?? []).map((faculty) => facultyNode(faculty, labels))

  return [
    {
      id: UNIVERSITY_ID,
      kind: 'university',
      label: labels.university,
      searchText: haystack(labels.university),
      children: faculties,
    },
  ]
}

/**
 * Client-side search over the whole tree (FR-PCM-002 — the browse endpoint ships
 * the whole payload; filtering never round-trips). Keeps a node when it matches the
 * query or has a kept descendant: a matched branch's ancestors are returned in
 * `expandedIds` so the match is revealed, while a node that matches on its own name
 * is kept with its full subtree (collapsed) so it can still be explored. An empty
 * query returns the tree untouched.
 */
export function filterTree(
  nodes: TreeNode[],
  rawQuery: string,
): { nodes: TreeNode[]; expandedIds: string[] } {
  const query = rawQuery.trim().toLowerCase()
  if (query === '') {
    return { nodes, expandedIds: [] }
  }

  const expandedIds: string[] = []

  const visit = (node: TreeNode): TreeNode | null => {
    const keptChildren = node.children
      .map(visit)
      .filter((child): child is TreeNode => child !== null)

    if (keptChildren.length > 0) {
      // On the path to a deeper match — reveal it.
      expandedIds.push(node.id)
      return { ...node, children: keptChildren }
    }
    if (node.searchText.includes(query)) {
      // The match itself — keep its full subtree, collapsed.
      return node
    }
    return null
  }

  const filtered = nodes.map(visit).filter((node): node is TreeNode => node !== null)

  return { nodes: filtered, expandedIds }
}
