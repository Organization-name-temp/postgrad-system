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
  noneLabel,
  onSearchChange,
}: {
  value: string
  onChange: (id: string) => void
  label: string
  enabled?: boolean
  noneLabel?: string
  onSearchChange?: (searching: boolean) => void
}) {
  const { t, i18n } = useTranslation('catalog')
  const [search, setSearch] = useState('')
  const [input, setInput] = useState('')
  const [searching, setSearching] = useState(false)
  const results = useListDepartments(
    { q: search || undefined, per_page: 100 },
    { query: { enabled } },
  )
  const resolved = useShowADepartment(Number(value), {
    query: { enabled: enabled && value !== '' },
  })
  const none = noneLabel ? { id: 0, code: '', name_en: noneLabel, name_ar: noneLabel } : null
  const options = none ? [none, ...(results.data?.data ?? [])] : (results.data?.data ?? [])
  const selected =
    (value === '' && none ? none : options.find((department) => String(department.id) === value)) ??
    (String(resolved.data?.data?.id) === value ? resolved.data?.data : null) ??
    null
  const selectedValue = searching ? null : selected
  const selectedLabel = selectedValue
    ? pickLocaleName(selectedValue, i18n.language, selectedValue.code ?? '')
    : null
  useEffect(() => {
    if (selectedLabel !== null) setInput(selectedLabel)
  }, [selectedLabel])
  useEffect(() => {
    if (!enabled) {
      setSearch('')
      setInput('')
      setSearching(false)
    }
  }, [enabled])

  return (
    <Autocomplete
      options={options}
      value={selectedValue}
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
          setSearching(true)
          onChange('')
          onSearchChange?.(true)
        }
      }}
      onChange={(_event, department) => {
        setSearching(department === null)
        onSearchChange?.(department === null)
        onChange(department?.id !== undefined && department.id !== 0 ? String(department.id) : '')
      }}
      renderInput={(params) => (
        <TextField
          {...params}
          label={label}
          required={!noneLabel}
          fullWidth
          error={results.isError || resolved.isError}
          helperText={results.isError || resolved.isError ? t('references.error') : undefined}
        />
      )}
    />
  )
}
