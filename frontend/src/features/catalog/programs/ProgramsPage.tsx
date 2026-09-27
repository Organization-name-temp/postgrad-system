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
import {
  exportPrograms,
  useListDepartments,
  useListPrograms,
} from '../../../shared/api/generated/endpoints'
import { usePermissions } from '../../../shared/access/usePermissions'
import { PERMISSION } from '../../../shared/access/permissions'
import { pickLocaleName } from '../localeName'
import { ProgramRow } from './ProgramRow'
import { ProgramFormDialog } from './ProgramFormDialog'

import { useCollectionQuery } from '../../../shared/ui/useCollectionQuery'
import { downloadBlob } from '../../../shared/downloadBlob'
import { ReferenceCollectionControls } from '../ReferenceCollectionControls'

/**
 * The Programs admin screen. Everyone with catalog.view sees the list (code, name,
 * owning Department, degree level, per-Category credit split, status); only
 * catalog.manage holders see the New / edit / activate / deactivate controls — the
 * API enforces the same split (ADR-0008). Departments are fetched alongside to
 * resolve each Program's Department name and to populate the create dialog's picker.
 */
export function ProgramsPage() {
  const { t, i18n } = useTranslation('catalog')
  const { hasPermission } = usePermissions()
  const canManage = hasPermission(PERMISSION.manageCatalog)

  const collection = useCollectionQuery()
  const { data, isPending, isError } = useListPrograms(collection.params)
  const programs = data?.data ?? []
  const { data: departmentsData } = useListDepartments()
  const departments = departmentsData?.data ?? []

  const departmentName = (id?: number): string => {
    const department = departments.find((item) => item.id === id)
    return department ? pickLocaleName(department, i18n.language, department.code ?? '') : ''
  }

  // The list creates Programs; editing a Program's attributes and Curriculum happens
  // on its own edit page (each row's edit action links there).
  const [dialogOpen, setDialogOpen] = useState(false)

  return (
    <>
      <Stack
        direction="row"
        sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 3, gap: 2 }}
      >
        <Typography variant="h4" component="h1">
          {t('programs.title')}
        </Typography>
        {canManage && (
          <Button variant="contained" startIcon={<AddIcon />} onClick={() => setDialogOpen(true)}>
            {t('programs.new')}
          </Button>
        )}
      </Stack>

      <ReferenceCollectionControls
        collection={collection}
        meta={isError ? undefined : data?.meta}
        fields={[
          {
            key: 'department_id',
            label: t('programs.department'),
            options: [
              { value: '', label: t('filters.all') },
              ...departments.map((item) => ({
                value: String(item.id),
                label: pickLocaleName(item, i18n.language, item.code ?? ''),
              })),
            ],
          },
          {
            key: 'degree_level',
            label: t('programs.degreeLevel'),
            options: [
              { value: '', label: t('filters.all') },
              { value: 'MSc', label: t('programs.degreeLevels.MSc') },
              { value: 'PhD', label: t('programs.degreeLevels.PhD') },
            ],
          },
        ]}
        onExport={async () =>
          downloadBlob(await exportPrograms(collection.params), 'programs.xlsx')
        }
      />

      {isPending && <CircularProgress aria-label={t('programs.loading')} />}
      {isError && <Alert severity="error">{t('programs.loadError')}</Alert>}

      {!isPending && !isError && programs.length === 0 && (
        <Typography color="text.secondary">{t('programs.empty')}</Typography>
      )}

      {programs.length > 0 && (
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('programs.code')}</TableCell>
                <TableCell>{t('programs.name')}</TableCell>
                <TableCell>{t('programs.department')}</TableCell>
                <TableCell>{t('programs.degreeLevel')}</TableCell>
                <TableCell>{t('programs.credits')}</TableCell>
                <TableCell>{t('programs.statusHeader')}</TableCell>
                {canManage && <TableCell align="right">{t('programs.actions')}</TableCell>}
              </TableRow>
            </TableHead>
            <TableBody>
              {programs.map((program) => (
                <ProgramRow
                  key={program.id}
                  program={program}
                  departmentName={departmentName(program.department_id)}
                  canManage={canManage}
                />
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      <ProgramFormDialog open={dialogOpen} onClose={() => setDialogOpen(false)} />
    </>
  )
}
