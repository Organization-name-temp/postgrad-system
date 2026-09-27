import InboxOutlined from '@mui/icons-material/InboxOutlined'
import { LtrValue } from './RecordValue'
import { transcriptPrintSx } from './transcriptPrint'
import { Link } from 'react-router-dom'
import { useState } from 'react'
import {
  Alert,
  Box,
  Button,
  MenuItem,
  Pagination,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import {
  useCurrentUnofficialTranscript,
  useTranscriptStudentPicker,
  useTranscriptServiceAvailability,
  exportTranscriptStudentPicker,
} from '../../shared/api/generated/endpoints'
import { downloadBlob } from '../../shared/downloadBlob'
import { TranscriptDocument } from './TranscriptDocument'

export function TranscriptPage() {
  const { t, i18n } = useTranslation('records')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<number>()
  const [exportError, setExportError] = useState(false)
  const params = { q: search, page, per_page: 25 }
  const picker = useTranscriptStudentPicker(params)
  const studentId = selected ?? picker.data?.data?.[0]?.id
  const record = useCurrentUnofficialTranscript(studentId ?? 0, {
    query: { enabled: Boolean(studentId) },
  })
  const service = useTranscriptServiceAvailability()
  return (
    <Box sx={transcriptPrintSx}>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('title')}
      </Typography>
      <Stack spacing={2} sx={{ mb: 3, '@media print': { display: 'none' } }}>
        <TextField
          label={t('search')}
          value={search}
          onChange={(event) => {
            setSearch(event.target.value)
            setPage(1)
            setSelected(undefined)
          }}
        />
        <TextField
          select
          label={t('student')}
          value={studentId ?? ''}
          onChange={(event) => setSelected(Number(event.target.value))}
        >
          {(picker.data?.data ?? []).map((student) => (
            <MenuItem key={student.id} value={student.id}>
              <LtrValue>{student.university_id}</LtrValue> ·{' '}
              {i18n.language === 'ar' ? student.name_ar : student.name_en}
            </MenuItem>
          ))}
        </TextField>
        {!picker.isPending && !picker.isError && picker.data?.data?.length === 0 && (
          <Alert severity="info" icon={<InboxOutlined />}>
            {t('service.noStudents')}
          </Alert>
        )}
        <Pagination
          count={picker.data?.meta?.last_page ?? 1}
          page={page}
          onChange={(_, value) => {
            setPage(value)
            setSelected(undefined)
          }}
        />
        <Stack direction="row" spacing={1}>
          <Button component={Link} to="/records/requests">
            {t('service.title')}
          </Button>
          <Button component={Link} to="/records/history">
            {t('service.history')}
          </Button>
          <Button onClick={() => window.print()} disabled={!record.data?.data}>
            {t('print')}
          </Button>
          <Button
            onClick={() => {
              setExportError(false)
              void exportTranscriptStudentPicker(params)
                .then((blob) => downloadBlob(blob, 'transcript-students.xlsx'))
                .catch(() => setExportError(true))
            }}
          >
            {t('exportStudents')}
          </Button>
        </Stack>
        {service.data?.data && (
          <Alert severity={service.data.data.available ? 'info' : 'warning'}>
            {service.data.data.available
              ? t('feeAvailable', { fee: service.data.data.fee })
              : t('serviceUnavailable')}
          </Alert>
        )}
        {(picker.isError || record.isError || exportError) && (
          <Alert severity="error">{t('loadError')}</Alert>
        )}
        {(picker.isPending || (studentId && record.isPending)) && (
          <Typography>{t('loading')}</Typography>
        )}
      </Stack>
      {record.data?.data && <TranscriptDocument record={record.data.data} />}
    </Box>
  )
}
