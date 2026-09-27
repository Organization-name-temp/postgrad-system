import { useSearchParams } from 'react-router-dom'

/** The URL is the source of truth for collection investigations and drill-through. */
export function useCollectionQuery(defaultPerPage = 25, namespace = '') {
  const [urlParams, setSearchParams] = useSearchParams()
  const prefix = namespace ? `${namespace}_` : ''
  // A page can host independent collections; only its URL keys are namespaced.
  // Each caller receives the same flat API criteria as a single-collection page.
  const searchParams = new URLSearchParams(
    [...urlParams]
      .filter(([key]) => key.startsWith(prefix))
      .map(([key, value]) => [key.slice(prefix.length), value]),
  )
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
        if (value === undefined || value === '') next.delete(prefix + key)
        else next.set(prefix + key, value)
      }
      next.delete(prefix + 'page')
      return next
    })
  }

  function setPage(page: number) {
    setSearchParams((previous) => {
      const next = new URLSearchParams(previous)
      next.set(prefix + 'page', String(page))
      return next
    })
  }

  return { params, searchParams, setCriteria, setPage }
}
