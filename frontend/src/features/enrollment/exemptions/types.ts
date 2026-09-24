import type {
  AdvisorManualExemptionQueue200DataItem,
  DepartmentHeadManualExemptionQueue200DataItem,
  MyManualExemptionRequests200DataItem,
} from '../../../shared/api/generated/endpoints'

export type StudentExemptionRequestRow = MyManualExemptionRequests200DataItem
export type ReviewerExemptionRequestRow = Omit<
  AdvisorManualExemptionQueue200DataItem,
  'decision'
> & {
  decision?: DepartmentHeadManualExemptionQueue200DataItem['decision']
}
