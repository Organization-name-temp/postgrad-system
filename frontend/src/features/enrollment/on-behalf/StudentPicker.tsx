import { Autocomplete, TextField } from '@mui/material'
import { useTranslation } from 'react-i18next'
import type { TheStudentsTheComposerMayRegisterOnBehalfOf200DataItem as ComposableStudent } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'

interface StudentPickerProps {
  students: ComposableStudent[]
  value: ComposableStudent | null
  onChange: (student: ComposableStudent | null) => void
  disabled?: boolean
}

/**
 * The register-for-student picker (ticket 09): a searchable Autocomplete over the students
 * the composer may register for — an Advisor's advisees, or every student for Student
 * Affairs / an administrator (the server scopes the list). Each option shows the student's
 * name, University ID and Program so a large Student Affairs list stays legible.
 */
export function StudentPicker({ students, value, onChange, disabled }: StudentPickerProps) {
  const { t, i18n } = useTranslation('enrollment')

  const programName = (student: ComposableStudent): string => {
    const program = student.program
    return program ? pickLocaleName(program, i18n.language, program.code ?? '') : ''
  }

  return (
    <Autocomplete
      options={students}
      value={value}
      onChange={(_event, next) => onChange(next)}
      disabled={disabled}
      getOptionLabel={(student) => `${student.name ?? ''} · ${student.university_id ?? ''}`}
      isOptionEqualToValue={(option, selected) => option.student_id === selected.student_id}
      renderOption={(props, student) => {
        const { key, ...rest } = props as typeof props & { key: string }
        return (
          <li key={key} {...rest}>
            <span>
              <strong>{student.name}</strong> · {student.university_id}
              {programName(student) && (
                <span style={{ opacity: 0.7 }}> — {programName(student)}</span>
              )}
            </span>
          </li>
        )
      }}
      renderInput={(params) => (
        <TextField {...params} label={t('onBehalf.pickStudent')} sx={{ maxWidth: 520 }} />
      )}
      sx={{ maxWidth: 520 }}
    />
  )
}
