import { useEffect, useState } from 'react'
import { Autocomplete, TextField } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useListDepartments, useShowADepartment } from '../../shared/api/generated/endpoints'
import { pickLocaleName } from './localeName'

/** Department references are searched and resolved through their owning collection. */
export function DepartmentPicker({
  value,
  onChange,
  label,
  enabled = true,
}: {
  value: string
  onChange: (id: string) => void
  label: string
  enabled?: boolean
}) {
  const { t, i18n } = useTranslation('catalog')
  const [search, setSearch] = useState('')
  const [input, setInput] = useState('')
  const results = useListDepartments(
    { q: search || undefined, per_page: 100 },
    { query: { enabled } },
  )
  const resolved = useShowADepartment(Number(value), {
    query: { enabled: enabled && value !== '' },
  })
  const options = results.data?.data ?? []
  const selected =
    options.find((department) => String(department.id) === value) ??
    (String(resolved.data?.data?.id) === value ? resolved.data?.data : null) ??
    null
  const selectedLabel = selected
    ? pickLocaleName(selected, i18n.language, selected.code ?? '')
    : null
  useEffect(() => {
    if (selectedLabel !== null) setInput(selectedLabel)
  }, [selectedLabel])
  useEffect(() => {
    if (!enabled) {
      setSearch('')
      setInput('')
    }
  }, [enabled])

  return (
    <Autocomplete
      options={options}
      value={selected}
      inputValue={input}
      getOptionLabel={(department) =>
        pickLocaleName(department, i18n.language, department.code ?? '')
      }
      getOptionKey={(department) => department.id ?? ''}
      isOptionEqualToValue={(option, selection) => option.id === selection.id}
      filterOptions={(rows) => rows}
      loading={results.isFetching}
      loadingText={t('references.loading')}
      noOptionsText={results.isError ? t('references.error') : t('references.noMatch')}
      clearText={t('references.clear')}
      openText={t('references.open')}
      closeText={t('references.close')}
      onInputChange={(_event, text, reason) => {
        if (reason === 'input' || reason === 'clear') {
          setInput(text)
          setSearch(text)
          onChange('')
        }
      }}
      onChange={(_event, department) =>
        onChange(department?.id !== undefined ? String(department.id) : '')
      }
      renderInput={(params) => (
        <TextField
          {...params}
          label={label}
          required
          fullWidth
          error={results.isError || resolved.isError}
          helperText={results.isError || resolved.isError ? t('references.error') : undefined}
        />
      )}
    />
  )
}
