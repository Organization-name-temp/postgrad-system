import { useMemo, useState } from 'react'
import { Alert, Box, Button, Chip, CircularProgress, Stack, Typography } from '@mui/material'
import { SimpleTreeView } from '@mui/x-tree-view/SimpleTreeView'
import { TreeItem } from '@mui/x-tree-view/TreeItem'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  getMyApplicationQueryKey,
  useChoosePrograms,
  useTheCatalogBrowseTree,
} from '../../../shared/api/generated/endpoints'
import type { MyApplication200Data } from '../../../shared/api/generated/endpoints'
import { apiErrorMessage, apiFieldErrors } from '../../../shared/api/errors'
import { buildProgramTree, programsById } from './programSelect'
import type { ProgramNode } from './programSelect'

interface ProgramStepProps {
  application: MyApplication200Data
  onBack: () => void
  onContinue: () => void
}

/**
 * Step 4 of the applicant wizard: Program selection (FR-ADM-004). The applicant
 * browses E-JUST's Programs in the Faculty → Department → Program tree — the same
 * Phase-1 catalog read the browse screen uses (ADR-0012) — and picks one primary
 * and an optional alternative. The server validates each choice (exists, matches
 * the degree applied for, passes the qualification rule); a rejection is shown
 * inline. Ids are opaque references, resolved to labels only for display.
 */
export function ProgramStep({ application, onBack, onContinue }: ProgramStepProps) {
  const { t, i18n } = useTranslation('admissions')
  const queryClient = useQueryClient()

  const { data, isPending, isError } = useTheCatalogBrowseTree()
  const choose = useChoosePrograms()

  const [primaryId, setPrimaryId] = useState<number | undefined>(
    application.primary_program_id ?? undefined,
  )
  const [alternativeId, setAlternativeId] = useState<number | undefined>(
    application.alternative_program_id ?? undefined,
  )

  const nodes = useMemo(
    () =>
      buildProgramTree(data?.data, {
        lang: i18n.language,
        program: (name, degree) => `${name} · ${degree}`,
      }),
    [data, i18n.language],
  )
  const byId = useMemo(() => programsById(nodes), [nodes])

  // Faculties and departments start expanded so the (small) list of Programs is
  // visible without hunting; program leaves have no children to expand.
  const defaultExpanded = useMemo(() => {
    const ids: string[] = []
    const walk = (list: ProgramNode[]): void => {
      for (const node of list) {
        if (node.kind !== 'program') {
          ids.push(node.id)
          walk(node.children)
        }
      }
    }
    walk(nodes)
    return ids
  }, [nodes])

  const fieldErrors = apiFieldErrors(choose.error)
  const generalError = choose.isError
    ? (apiErrorMessage(choose.error) ?? t('program.saveError'))
    : undefined

  const setPrimary = (id: number) => {
    setPrimaryId(id)
    // The alternative must differ from the primary; clear it if it now collides.
    setAlternativeId((current) => (current === id ? undefined : current))
  }

  const setAlternative = (id: number) =>
    setAlternativeId((current) => (current === id ? undefined : id))

  const handleContinue = () => {
    if (primaryId === undefined) return
    choose.mutate(
      { data: { primary_program_id: primaryId, alternative_program_id: alternativeId } },
      {
        onSuccess: async () => {
          await queryClient.invalidateQueries({ queryKey: getMyApplicationQueryKey() })
          onContinue()
        },
      },
    )
  }

  const selectionRow = (label: string, id: number | undefined) => (
    <Typography variant="body2">
      <Box component="span" sx={{ fontWeight: 600 }}>
        {label}:
      </Box>{' '}
      {id !== undefined ? (byId.get(id)?.label ?? `#${id}`) : t('program.none')}
    </Typography>
  )

  const renderNode = (node: ProgramNode) => (
    <TreeItem
      key={node.id}
      itemId={node.id}
      label={
        node.program ? (
          <Stack
            direction="row"
            spacing={1}
            sx={{
              alignItems: 'center',
              justifyContent: 'space-between',
              flexWrap: 'wrap',
              py: 0.5,
            }}
          >
            <Box component="span">{node.label}</Box>
            <Stack direction="row" spacing={1}>
              <Chip
                label={t('program.setPrimary')}
                size="small"
                color={primaryId === node.program.id ? 'primary' : 'default'}
                variant={primaryId === node.program.id ? 'filled' : 'outlined'}
                onClick={(event) => {
                  event.stopPropagation()
                  setPrimary(node.program!.id)
                }}
              />
              <Chip
                label={t('program.setAlternative')}
                size="small"
                color={alternativeId === node.program.id ? 'secondary' : 'default'}
                variant={alternativeId === node.program.id ? 'filled' : 'outlined'}
                disabled={primaryId === node.program.id}
                onClick={(event) => {
                  event.stopPropagation()
                  setAlternative(node.program!.id)
                }}
              />
            </Stack>
          </Stack>
        ) : (
          node.label
        )
      }
    >
      {node.children.map(renderNode)}
    </TreeItem>
  )

  return (
    <Box>
      <Typography variant="h6" gutterBottom>
        {t('program.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('program.intro')}
      </Typography>

      <Stack spacing={0.5} sx={{ my: 2 }}>
        {selectionRow(t('program.primaryLabel'), primaryId)}
        {selectionRow(t('program.alternativeLabel'), alternativeId)}
      </Stack>

      {generalError && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {generalError}
        </Alert>
      )}
      {fieldErrors.primary_program_id && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {fieldErrors.primary_program_id[0]}
        </Alert>
      )}
      {fieldErrors.alternative_program_id && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {fieldErrors.alternative_program_id[0]}
        </Alert>
      )}

      {isPending && <CircularProgress aria-label={t('program.loading')} />}
      {isError && <Alert severity="error">{t('program.loadError')}</Alert>}

      {!isPending && !isError && (
        <SimpleTreeView defaultExpandedItems={defaultExpanded} aria-label={t('program.title')}>
          {nodes.map(renderNode)}
        </SimpleTreeView>
      )}

      <Stack direction="row" spacing={2} sx={{ mt: 3 }}>
        <Button variant="outlined" onClick={onBack} disabled={choose.isPending}>
          {t('program.back')}
        </Button>
        <Button
          variant="contained"
          onClick={handleContinue}
          disabled={primaryId === undefined || choose.isPending}
        >
          {choose.isPending ? t('program.saving') : t('program.saveContinue')}
        </Button>
      </Stack>
    </Box>
  )
}
