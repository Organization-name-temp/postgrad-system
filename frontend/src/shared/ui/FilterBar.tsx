import { Autocomplete, MenuItem, Stack, TextField } from '@mui/material'

export interface FilterOption {
  value: string
  label: string
}

export interface FilterField {
  key: string
  label: string
  type?: 'text' | 'number' | 'date'
  options?: readonly { value: string; label: string }[]
  reference?: {
    options: readonly FilterOption[]
    selected?: FilterOption
    query: string | null
    onSearch: (query: string | null) => void
    loading: boolean
    noOptionsText: string
    loadingText?: string
    clearText: string
    openText: string
    closeText: string
  }
}

/** Owners supply fields and translated labels; the shared control knows no domain. */
export function FilterBar({
  fields,
  values,
  onChange,
}: {
  fields: readonly FilterField[]
  values: Record<string, string | number | undefined>
  onChange: (criteria: Record<string, string>) => void
}) {
  return (
    <Stack
      direction="row"
      useFlexGap
      spacing={2}
      sx={{ flexWrap: 'wrap', '@media print': { display: 'none' } }}
    >
      {fields.map((field) =>
        field.reference ? (
          <Autocomplete
            key={field.key}
            options={field.reference.options}
            value={field.reference.selected ?? null}
            inputValue={field.reference.query ?? field.reference.selected?.label ?? ''}
            filterOptions={(options) => options}
            isOptionEqualToValue={(option, selected) => option.value === selected.value}
            getOptionLabel={(option) => option.label}
            onInputChange={(_, value, reason) => {
              if (reason === 'input') field.reference?.onSearch(value)
            }}
            onChange={(event, option, reason) => {
              // MUI also emits "clear" while typing an empty search. Only its
              // explicit clear button changes the effective URL criterion.
              if (reason === 'clear' && event.type !== 'click') return
              field.reference?.onSearch(null)
              onChange({ [field.key]: option?.value ?? '' })
            }}
            loading={field.reference.loading}
            loadingText={field.reference.loadingText}
            noOptionsText={field.reference.noOptionsText}
            clearText={field.reference.clearText}
            openText={field.reference.openText}
            closeText={field.reference.closeText}
            renderInput={(params) => <TextField {...params} label={field.label} />}
            sx={{ flex: '1 1 12rem', minWidth: 0 }}
          />
        ) : (
          <TextField
            key={field.key}
            label={field.label}
            select={Boolean(field.options)}
            type={field.type ?? 'text'}
            value={values[field.key] ?? ''}
            onChange={(event) => onChange({ [field.key]: event.target.value })}
            slotProps={{ inputLabel: field.type === 'date' ? { shrink: true } : undefined }}
            sx={{ flex: '1 1 12rem', minWidth: 0 }}
          >
            {field.options &&
              values[field.key] !== undefined &&
              values[field.key] !== '' &&
              !field.options.some((option) => option.value === String(values[field.key])) && (
                <MenuItem value={String(values[field.key])}>
                  {String(values[field.key])
                    .split(',')
                    .map((value) => {
                      const token = value.trim()
                      return field.options?.find((option) => option.value === token)?.label ?? token
                    })
                    .join(', ')}
                </MenuItem>
              )}
            {field.options?.map((option) => (
              <MenuItem key={option.value} value={option.value}>
                {option.label}
              </MenuItem>
            ))}
          </TextField>
        ),
      )}
    </Stack>
  )
}
