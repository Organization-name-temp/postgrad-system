import { ADMIN_ROLES, ROLE } from '../../shared/access/roles'

/** UX mirror of the owner cohort gate; backend authorization remains authoritative. */
export const ACADEMIC_REPORT_ROLES = [
  ROLE.studentAffairs,
  ROLE.council,
  ROLE.advisor,
  ROLE.dean,
  ROLE.departmentHead,
  ...ADMIN_ROLES,
]
