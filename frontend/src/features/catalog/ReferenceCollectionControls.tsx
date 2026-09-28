import { useTranslation } from 'react-i18next'
import { FilterBar } from '../../shared/ui/FilterBar'
import type { FilterField } from '../../shared/ui/FilterBar'
import { DataToolbar } from '../../shared/ui/DataToolbar'
import type { useCollectionQuery } from '../../shared/ui/useCollectionQuery'

/** Catalog-owned criteria; shared presentation remains free of Catalog vocabulary. */
export function ReferenceCollectionControls({
  collection,
  meta,
  fields = [],
  onExport,
}: {
  collection: ReturnType<typeof useCollectionQuery>
  meta?: { total?: number; current_page?: number; last_page?: number }
  fields?: readonly FilterField[]
  onExport: () => Promise<void>
}) {
  const { t } = useTranslation('catalog')
  return (
    <>
      <FilterBar
        values={collection.params}
        onChange={collection.setCriteria}
        fields={[
          { key: 'q', label: t('filters.search') },
          ...fields,
          {
            key: 'is_active',
            label: t('filters.activeState'),
            options: [
              { value: '', label: t('filters.all') },
              { value: 'true', label: t('status.active') },
              { value: 'false', label: t('status.inactive') },
            ],
          },
          {
            key: 'sort',
            label: t('filters.sort'),
            options: [
              { value: '', label: t('filters.code') },
              { value: 'name_en', label: t('filters.englishName') },
              { value: 'name_ar', label: t('filters.arabicName') },
            ],
          },
        ]}
      />
      {meta && (
        <DataToolbar
          total={meta.total ?? 0}
          page={meta.current_page ?? collection.params.page}
          lastPage={meta.last_page ?? 1}
          onPageChange={collection.setPage}
          onExport={onExport}
        />
      )}
    </>
  )
}
