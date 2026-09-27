import { useState } from 'react'
import {
  Alert,
  Button,
  CircularProgress,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import AddIcon from '@mui/icons-material/Add'
import { useTranslation } from 'react-i18next'
import { exportFaculties, useListFaculties } from '../../../shared/api/generated/endpoints'
import type { ListFaculties200DataItem } from '../../../shared/api/generated/endpoints'
import { usePermissions } from '../../../shared/access/usePermissions'
import { PERMISSION } from '../../../shared/access/permissions'
import { FacultyRow } from './FacultyRow'
import { FacultyFormDialog } from './FacultyFormDialog'

import { useCollectionQuery } from '../../../shared/ui/useCollectionQuery'
import { downloadBlob } from '../../../shared/downloadBlob'
import { ReferenceCollectionControls } from '../ReferenceCollectionControls'

/**
 * The Faculties admin screen. Everyone with catalog.view sees the list (code,
 * name in the active locale, active/inactive status); only catalog.manage holders
 * see the New / edit / activate / deactivate controls — the API enforces the same
 * split regardless (ADR-0008).
 */
export function FacultiesPage() {
  const { t } = useTranslation('catalog')
  const { hasPermission } = usePermissions()
  const canManage = hasPermission(PERMISSION.manageCatalog)

  const collection = useCollectionQuery()
  const { data, isPending, isError } = useListFaculties(collection.params)
  const faculties = data?.data ?? []

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<ListFaculties200DataItem | undefined>(undefined)

  const openCreate = () => {
    setEditing(undefined)
    setDialogOpen(true)
  }
  const openEdit = (faculty: ListFaculties200DataItem) => {
    setEditing(faculty)
    setDialogOpen(true)
  }

  return (
    <>
      <Stack
        direction="row"
        sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 3, gap: 2 }}
      >
        <Typography variant="h4" component="h1">
          {t('faculties.title')}
        </Typography>
        {canManage && (
          <Button variant="contained" startIcon={<AddIcon />} onClick={openCreate}>
            {t('faculties.new')}
          </Button>
        )}
      </Stack>

      <ReferenceCollectionControls
        collection={collection}
        meta={isError ? undefined : data?.meta}

        onExport={async () =>
          downloadBlob(await exportFaculties(collection.params), 'faculties.xlsx')
        }
      />

      {isPending && <CircularProgress aria-label={t('faculties.loading')} />}
      {isError && <Alert severity="error">{t('faculties.loadError')}</Alert>}

      {!isPending && !isError && faculties.length === 0 && (
        <Typography color="text.secondary">{t('faculties.empty')}</Typography>
      )}

      {faculties.length > 0 && (
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('faculties.code')}</TableCell>
                <TableCell>{t('faculties.name')}</TableCell>
                <TableCell>{t('faculties.statusHeader')}</TableCell>
                {canManage && <TableCell align="right">{t('faculties.actions')}</TableCell>}
              </TableRow>
            </TableHead>
            <TableBody>
              {faculties.map((faculty) => (
                <FacultyRow
                  key={faculty.id}
                  faculty={faculty}
                  canManage={canManage}
                  onEdit={openEdit}
                />
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      <FacultyFormDialog open={dialogOpen} onClose={() => setDialogOpen(false)} faculty={editing} />
    </>
  )
}
