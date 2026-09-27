import { MenuItem, Stack, TextField } from '@mui/material'

export interface FilterField {
  key: string
  label: string
  type?: 'text' | 'number' | 'date'
  options?: readonly { value: string; label: string }[]
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
      {fields.map((field) => (
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
          {field.options?.map((option) => (
            <MenuItem key={option.value} value={option.value}>
              {option.label}
            </MenuItem>
          ))}
        </TextField>
      ))}
    </Stack>
  )
}
