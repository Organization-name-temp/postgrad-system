import { useEffect, useState } from 'react'
import { Autocomplete, TextField } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useListFaculties, useShowAFaculty } from '../../shared/api/generated/endpoints'
import { pickLocaleName } from './localeName'

/** Faculty references are searched and resolved through their owning collection. */
export function FacultyPicker({
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
  const results = useListFaculties(
    { q: search || undefined, per_page: 100 },
    { query: { enabled } },
  )
  const resolved = useShowAFaculty(Number(value), { query: { enabled: enabled && value !== '' } })
  const options = results.data?.data ?? []
  const selected =
    options.find((faculty) => String(faculty.id) === value) ??
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
      getOptionLabel={(faculty) => pickLocaleName(faculty, i18n.language, faculty.code ?? '')}
      getOptionKey={(faculty) => faculty.id ?? ''}
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
      onChange={(_event, faculty) => onChange(faculty?.id !== undefined ? String(faculty.id) : '')}
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
