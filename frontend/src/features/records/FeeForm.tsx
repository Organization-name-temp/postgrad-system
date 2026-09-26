import { useState } from 'react'
import { Alert, Button, Stack, TextField } from '@mui/material'
import { useTranslation } from 'react-i18next'
import type {
  TranscriptFeeConfiguration200Data,
  ConfigureTranscriptFeeBody,
} from '../../shared/api/generated/endpoints'

export function FeeForm({
  config,
  save,
}: {
  config: TranscriptFeeConfiguration200Data
  save: (data: ConfigureTranscriptFeeBody) => Promise<void>
}) {
  const { t } = useTranslation('records')
  const [fee, setFee] = useState(config.fee ?? '')
  const [english, setEnglish] = useState(config.instructions_en ?? '')
  const [arabic, setArabic] = useState(config.instructions_ar ?? '')
  const [state, setState] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle')
  const valid =
    /^\d{1,7}(\.\d{1,2})?$/.test(fee) &&
    Number(fee) > 0 &&
    english.trim() !== '' &&
    arabic.trim() !== ''
  return (
    <Stack
      component="form"
      spacing={2}
      onSubmit={(event) => {
        event.preventDefault()
        if (!valid || state === 'saving') return
        setState('saving')
        void save({ fee, instructions_en: english, instructions_ar: arabic })
          .then(() => setState('saved'))
          .catch(() => setState('error'))
      }}
    >
      {state === 'error' && <Alert severity="error">{t('fee.error')}</Alert>}
      {state === 'saved' && <Alert severity="success">{t('fee.saved')}</Alert>}
      <TextField
        label={t('fee.amount')}
        value={fee}
        onChange={(e) => setFee(e.target.value)}
        required
        slotProps={{ htmlInput: { inputMode: 'decimal', dir: 'ltr' } }}
      />
      <TextField
        label={t('fee.english')}
        value={english}
        onChange={(e) => setEnglish(e.target.value)}
        required
        multiline
        slotProps={{ htmlInput: { dir: 'ltr' } }}
      />
      <TextField
        label={t('fee.arabic')}
        value={arabic}
        onChange={(e) => setArabic(e.target.value)}
        required
        multiline
        slotProps={{ htmlInput: { dir: 'rtl' } }}
      />
      <Button type="submit" variant="contained" disabled={!valid || state === 'saving'}>
        {t('fee.save')}
      </Button>
    </Stack>
  )
}
