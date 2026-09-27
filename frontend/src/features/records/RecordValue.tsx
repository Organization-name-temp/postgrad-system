import { Box } from '@mui/material'
import type { ReactNode } from 'react'
import { Trans, useTranslation } from 'react-i18next'

export function LtrValue({ children }: { children?: ReactNode }) {
  return (
    <Box component="span" dir="ltr" sx={{ unicodeBidi: 'isolate' }}>
      {children}
    </Box>
  )
}

export function formatRecordTime(
  value: string | null | undefined,
  language: string,
  dateOnly = false,
) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat(
    language === 'ar' ? 'ar-EG-u-nu-latn' : 'en-GB',
    dateOnly
      ? { dateStyle: 'medium', timeZone: 'UTC' }
      : {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          hourCycle: 'h23',
          minute: '2-digit',
          second: '2-digit',
          timeZone: 'UTC',
          timeZoneName: 'short',
        },
  ).format(date)
}

export function RecordTime({
  value,
  dateOnly = false,
  language,
}: {
  value?: string | null
  dateOnly?: boolean
  language?: string
}) {
  const { i18n } = useTranslation()
  return <LtrValue>{formatRecordTime(value, language ?? i18n.language, dateOnly)}</LtrValue>
}

export function RecordActor({ id }: { id?: number | null }) {
  return (
    <Trans ns="records" i18nKey="service.actor" values={{ id }} components={{ id: <LtrValue /> }} />
  )
}
