import { useSearchParams } from 'react-router-dom'

/** The URL is the source of truth for collection investigations and drill-through. */
export function useCollectionQuery(defaultPerPage = 25) {
  const [searchParams, setSearchParams] = useSearchParams()
  const positiveInteger = (value: string | null, fallback: number) => {
    const number = Number(value)
    return Number.isSafeInteger(number) && number > 0 ? number : fallback
  }
  const params = {
    ...Object.fromEntries(searchParams),
    page: positiveInteger(searchParams.get('page'), 1),
    per_page: Math.min(100, positiveInteger(searchParams.get('per_page'), defaultPerPage)),
  }

  function setCriteria(criteria: Record<string, string | undefined>) {
    setSearchParams((previous) => {
      const next = new URLSearchParams(previous)
      for (const [key, value] of Object.entries(criteria)) {
        if (value === undefined || value === '') next.delete(key)
        else next.set(key, value)
      }
      next.delete('page')
      return next
    })
  }

  function setPage(page: number) {
    setSearchParams((previous) => {
      const next = new URLSearchParams(previous)
      next.set('page', String(page))
      return next
    })
  }

  return { params, searchParams, setCriteria, setPage }
}
