import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Alert,
  Box,
  CircularProgress,
  InputAdornment,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import SearchIcon from '@mui/icons-material/Search'
import { SimpleTreeView } from '@mui/x-tree-view/SimpleTreeView'
import { TreeItem } from '@mui/x-tree-view/TreeItem'
import { useTranslation } from 'react-i18next'
import { useTheCatalogBrowseTree } from '../../../shared/api/generated/endpoints'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { activeStatusChip } from '../statusChips'
import { buildCatalogTree, filterTree, UNIVERSITY_ID } from './treeModel'
import type { TreeNode } from './treeModel'

/**
 * The headline browse experience (FR-PCM-002): the whole University → Faculty →
 * Department → Program → Category → Course hierarchy in one interactive tree, from a
 * single read-model endpoint with client-side search/filter. Course leaves carry an
 * active/inactive StatusChip (never colour alone) and open the Course detail on
 * click. Anyone with catalog.view reaches it; every name shows in the active locale
 * and the whole thing flips to RTL in Arabic (the tree inherits theme.direction).
 */
export function CatalogTreePage() {
  const { t, i18n } = useTranslation('catalog')
  const navigate = useNavigate()

  const { data, isPending, isError } = useTheCatalogBrowseTree()

  const [query, setQuery] = useState('')
  // Manual expansion is only in effect when not searching; a search drives its own
  // expansion so matches are revealed.
  const [userExpanded, setUserExpanded] = useState<string[]>([UNIVERSITY_ID])

  const rootNodes = useMemo(
    () =>
      buildCatalogTree(data?.data, {
        lang: i18n.language,
        university: t('tree.university'),
        program: (name, degree) => `${name} · ${t(`programs.degreeLevels.${degree}`, degree)}`,
        category: (category, requiredCh) =>
          `${t(`curriculum.categories.${category}`, category)} · ${t('tree.requiredCh', { ch: requiredCh })}`,
      }),
    [data, i18n.language, t],
  )

  const { nodes, expandedIds } = useMemo(() => filterTree(rootNodes, query), [rootNodes, query])
  const searching = query.trim() !== ''
  const expandedItems = searching ? expandedIds : userExpanded

  // Course-leaf itemId → courseId, so a click can open the Course detail.
  const courseByItem = useMemo(() => {
    const map = new Map<string, number>()
    const walk = (list: TreeNode[]): void => {
      for (const node of list) {
        if (node.kind === 'course' && node.courseId !== undefined) {
          map.set(node.id, node.courseId)
        }
        walk(node.children)
      }
    }
    walk(rootNodes)
    return map
  }, [rootNodes])

  const chipLabels = { active: t('status.active'), inactive: t('status.inactive') }

  const renderNode = (node: TreeNode) => (
    <TreeItem
      key={node.id}
      itemId={node.id}
      label={
        node.kind === 'course' ? (
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center', py: 0.25 }}>
            <Box component="span">{node.label}</Box>
            <StatusChip {...activeStatusChip(node.isActive ?? false, chipLabels)} />
          </Stack>
        ) : (
          node.label
        )
      }
    >
      {node.children.map(renderNode)}
    </TreeItem>
  )

  // Once loaded, the tree always has the University root; "empty" means no matches.
  const noMatches = !isPending && !isError && searching && (nodes[0]?.children.length ?? 0) === 0

  return (
    <>
      <Typography variant="h4" component="h1" sx={{ mb: 3 }}>
        {t('tree.title')}
      </Typography>

      <TextField
        value={query}
        onChange={(event) => setQuery(event.target.value)}
        label={t('tree.search')}
        fullWidth
        sx={{ mb: 3, maxWidth: 480 }}
        slotProps={{
          input: {
            startAdornment: (
              <InputAdornment position="start">
                <SearchIcon fontSize="small" />
              </InputAdornment>
            ),
          },
        }}
      />

      {isPending && <CircularProgress aria-label={t('tree.loading')} />}
      {isError && <Alert severity="error">{t('tree.loadError')}</Alert>}

      {noMatches && (
        <Typography color="text.secondary">{t('tree.noResults', { query })}</Typography>
      )}

      {!isPending && !isError && !noMatches && (
        <SimpleTreeView
          expandedItems={expandedItems}
          onExpandedItemsChange={(_event, ids) => {
            if (!searching) {
              setUserExpanded(ids)
            }
          }}
          onItemClick={(_event, itemId) => {
            const courseId = courseByItem.get(itemId)
            if (courseId !== undefined) {
              void navigate(`/catalog/courses/${courseId}`)
            }
          }}
          aria-label={t('tree.title')}
        >
          {nodes.map(renderNode)}
        </SimpleTreeView>
      )}
    </>
  )
}
