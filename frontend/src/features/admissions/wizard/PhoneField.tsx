import { useMemo, useState } from 'react'
import { Autocomplete, Stack, TextField } from '@mui/material'
import { getCountries, getCountryCallingCode, parsePhoneNumber } from 'libphonenumber-js'
import type { CountryCode } from 'libphonenumber-js'
import { countryLabel } from './countries'

interface PhoneFieldProps {
  /** The stored value: canonical E.164 (e.g. `+201000000000`), or `''` when unset. */
  value: string
  /** Emits the recomputed E.164 (or `''` when the national number is cleared). */
  onChange: (value: string) => void
  /** Blur of the national-number input — drives RHF's on-touched validation. */
  onBlur: () => void
  label: string
  countryLabelText: string
  error?: boolean
  helperText?: string
  /** Locale for the country labels (EN/AR). */
  lang: string
}

interface PhoneCountry {
  country: CountryCode
  label: string
  callingCode: string
}

/** Parse a stored E.164 back into its country + national number, for seeding. */
function splitE164(value: string): { country: CountryCode | null; national: string } {
  if (value === '') return { country: null, national: '' }
  try {
    const parsed = parsePhoneNumber(value)
    return { country: parsed?.country ?? null, national: parsed?.nationalNumber ?? '' }
  } catch {
    return { country: null, national: '' }
  }
}

/**
 * A country-selector + national-number phone input backed by libphonenumber-js
 * (ADR-0014 / ticket 06). The applicant chooses a country and types the national
 * number; the field stores the canonical **E.164** form. Validity (a number that is
 * possible/valid for the chosen country) is enforced by the step's Zod schema and,
 * authoritatively, by the server's E.164 re-check. Egypt is the default country,
 * since most applicants are local.
 */
export function PhoneField({
  value,
  onChange,
  onBlur,
  label,
  countryLabelText,
  error,
  helperText,
  lang,
}: PhoneFieldProps) {
  const seed = useMemo(() => splitE164(value), [value])
  const [country, setCountry] = useState<CountryCode>(seed.country ?? 'EG')
  const [national, setNational] = useState(seed.national)

  // The supported calling-code countries, labelled and sorted for the picker.
  const options = useMemo<PhoneCountry[]>(
    () =>
      getCountries()
        .map((c) => ({
          country: c,
          label: countryLabel(c, lang),
          callingCode: getCountryCallingCode(c),
        }))
        .sort((a, b) => a.label.localeCompare(b.label, lang)),
    [lang],
  )
  // Always resolves — `country` defaults to a valid country and never leaves the list.
  const selected = options.find((option) => option.country === country) ?? options[0]

  /** Recompute and emit the E.164 from a country + national number. */
  const emit = (nextCountry: CountryCode, nextNational: string) => {
    const digits = nextNational.replace(/\D/g, '')
    onChange(digits === '' ? '' : `+${getCountryCallingCode(nextCountry)}${digits}`)
  }

  return (
    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ alignItems: 'flex-start' }}>
      <Autocomplete
        options={options}
        value={selected}
        disableClearable
        getOptionLabel={(option) => `${option.label} (+${option.callingCode})`}
        isOptionEqualToValue={(option, val) => option.country === val.country}
        onChange={(_event, option) => {
          if (!option) return
          setCountry(option.country)
          emit(option.country, national)
        }}
        sx={{ minWidth: 220 }}
        renderInput={(params) => <TextField {...params} label={countryLabelText} />}
      />
      <TextField
        label={label}
        type="tel"
        value={national}
        onChange={(event) => {
          setNational(event.target.value)
          emit(country, event.target.value)
        }}
        onBlur={onBlur}
        required
        fullWidth
        error={error}
        helperText={helperText}
      />
    </Stack>
  )
}
