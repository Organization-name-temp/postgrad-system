import { Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useAdmissionDecisionsCard } from '../../../shared/api/generated/endpoints'
import { DashboardCard } from '../../../shared/ui/DashboardCard'
import { tabularNums } from '../../../app/theme/fonts'

/**
 * Admission Decisions (Dashboard Card 3, ADR-0024): how many Applications are waiting on the
 * acting Dean's decision, linking into the decisions queue. Owned by the admissions feature
 * because Admissions owns the data; the app shell only arranges it.
 *
 * A Dean's decision is the last step before an Applicant becomes a Student, and until this Card
 * nothing told a Dean that applicants were waiting on them — the queue was only discoverable by
 * opening it.
 *
 * The count is **faculty-scoped on the server**, by the same derivation the decisions queue lists,
 * so the number here and the number there are the same number by construction. Gated in the
 * registry on `applicant.decide`, which the §3.2 matrix gives to the Dean alone; the endpoint
 * enforces it independently (ADR-0014).
 */
export function AdmissionDecisionsCard() {
  const { t } = useTranslation('admissions')
  const { data, isPending, isError, refetch } = useAdmissionDecisionsCard()
  const pending = data?.data?.pending_count ?? 0

  return (
    <DashboardCard
      title={t('cards.admissionDecisions.title')}
      isPending={isPending}
      isError={isError}
      errorText={t('cards.admissionDecisions.loadError')}
      onRetry={() => void refetch()}
      // Nothing awaiting a decision reads as good news, never as a bare 0.
      isEmpty={pending === 0}
      emptyText={t('cards.admissionDecisions.empty')}
      action={{ to: '/dean/decisions', label: t('cards.admissionDecisions.action') }}
      count={
        <Typography variant="h4" component="p" sx={{ ...tabularNums, fontWeight: 700 }}>
          {t('cards.admissionDecisions.pending', { count: pending })}
        </Typography>
      }
    />
  )
}
