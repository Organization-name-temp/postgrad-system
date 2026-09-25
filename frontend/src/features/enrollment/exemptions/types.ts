import type {
  AdvisorManualExemptionQueue200DataItem,
  MyManualExemptionRequests200DataItem,
} from '../../../shared/api/generated/endpoints'

export type StudentExemptionRequestRow = MyManualExemptionRequests200DataItem
export type ReviewerExemptionRequestRow = Omit<
  AdvisorManualExemptionQueue200DataItem,
  'decision'
> & {
  decision?: MyManualExemptionRequests200DataItem['decision']
}
