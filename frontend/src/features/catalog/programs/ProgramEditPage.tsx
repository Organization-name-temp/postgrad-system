import { useState } from 'react'
import { Link as RouterLink, useParams } from 'react-router-dom'
import {
  Alert,
  Box,
  Breadcrumbs,
  Button,
  CircularProgress,
  Divider,
  Link,
  Stack,
  Typography,
} from '@mui/material'
import EditIcon from '@mui/icons-material/Edit'
import { useTranslation } from 'react-i18next'
import {
  useListAProgramsVersionHistory,
  useShowAProgram,
} from '../../../shared/api/generated/endpoints'
import { usePermissions } from '../../../shared/access/usePermissions'
import { PERMISSION } from '../../../shared/access/permissions'
import { CurriculumPanel } from '../curriculum/CurriculumPanel'
import { VersionHistoryPanel } from '../versions/VersionHistoryPanel'
import { pickLocaleName } from '../localeName'
import { ProgramFormDialog } from './ProgramFormDialog'

/**
 * The Program edit page (routed at /catalog/programs/:programId) — the one place a
 * Program's attributes and its Curriculum are managed (user story 20). It shows the
 * Program header (name, code, degree level, per-Category credit split), an "Edit
 * details" button that reuses the create/edit dialog, and the CurriculumPanel.
 * Everyone with catalog.view can view; only catalog.manage sees the edit controls —
 * the API enforces the same split (ADR-0008).
 */
export function ProgramEditPage() {
  const { programId: programIdParam } = useParams()
  const programId = Number(programIdParam)
  const { t, i18n } = useTranslation('catalog')
  const { hasPermission } = usePermissions()
  const canManage = hasPermission(PERMISSION.manageCatalog)

  const { data, isPending, isError } = useShowAProgram(programId)
  const program = data?.data

  // History is a catalog.view read (story 35), so everyone who can reach the page
  // can see it — no manage gate.
  const versions = useListAProgramsVersionHistory(programId)

  const [detailsOpen, setDetailsOpen] = useState(false)

  if (isPending) {
    return <CircularProgress aria-label={t('programs.loading')} />
  }
  if (isError || !program) {
    return <Alert severity="error">{t('programs.loadError')}</Alert>
  }

  const name = pickLocaleName(program, i18n.language, program.code ?? '')
  const degreeLabel = program.degree_level ? t(`programs.degreeLevels.${program.degree_level}`) : ''
  const credits = t('programs.creditSplit', {
    prep: program.prep_required_ch ?? 0,
    core: program.core_required_ch ?? 0,
    elective: program.elective_required_ch ?? 0,
    total: program.total_required_ch ?? 0,
  })

  return (
    <>
      <Breadcrumbs sx={{ mb: 2 }}>
        <Link component={RouterLink} to="/catalog/programs" underline="hover" color="inherit">
          {t('programs.title')}
        </Link>
        <Typography color="text.primary">{program.code}</Typography>
      </Breadcrumbs>

      <Stack
        direction="row"
        sx={{ alignItems: 'flex-start', justifyContent: 'space-between', gap: 2, mb: 1 }}
      >
        <Box>
          <Typography variant="h4" component="h1">
            {name}
          </Typography>
          <Typography color="text.secondary">
            {program.code} · {degreeLabel} · {credits}
          </Typography>
        </Box>
        {canManage && (
          <Button variant="outlined" startIcon={<EditIcon />} onClick={() => setDetailsOpen(true)}>
            {t('programs.editDetails')}
          </Button>
        )}
      </Stack>

      <Divider sx={{ my: 3 }} />

      <CurriculumPanel programId={programId} canManage={canManage} />

      <Divider sx={{ my: 3 }} />
      <VersionHistoryPanel
        versions={versions.data?.data ?? []}
        isPending={versions.isPending}
        isError={versions.isError}
      />

      <ProgramFormDialog
        open={detailsOpen}
        onClose={() => setDetailsOpen(false)}
        program={program}
      />
    </>
  )
}
