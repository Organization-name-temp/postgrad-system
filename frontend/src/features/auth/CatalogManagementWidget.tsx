import { Alert, Card, CardContent, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useCatalogManagementDemo } from '../../shared/api/generated/endpoints'

/**
 * The permission-gated demo widget. Rendered only inside a PermissionGate for
 * `catalog.manage`, it then calls the matching gated endpoint — closing the RBAC
 * loop: the SPA mirrors the permission for UX, the API re-confirms it as the
 * sole authority.
 */
export function CatalogManagementWidget() {
  const { t } = useTranslation(['auth', 'common'])
  const { data, isPending, isError } = useCatalogManagementDemo()

  return (
    <Card variant="outlined" sx={{ mt: 3, borderColor: 'primary.main' }}>
      <CardContent>
        <Typography variant="subtitle1" gutterBottom>
          {t('catalog.title')}
        </Typography>
        {isPending && <Typography variant="body2">{t('common:loading')}</Typography>}
        {isError && <Alert severity="error">{t('catalog.error')}</Alert>}
        {data?.data?.message && <Typography variant="body2">{data.data.message}</Typography>}
      </CardContent>
    </Card>
  )
}
