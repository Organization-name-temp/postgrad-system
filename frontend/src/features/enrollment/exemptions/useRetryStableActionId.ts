import { useCallback, useRef } from 'react'

/** Keeps one idempotency key for retries of the same logical workflow action. */
export function useRetryStableActionId() {
  const current = useRef<{ key: string; id: string } | null>(null)

  const forAction = useCallback((key: string) => {
    if (current.current?.key !== key) current.current = { key, id: crypto.randomUUID() }
    return current.current.id
  }, [])

  const complete = useCallback((key: string) => {
    if (current.current?.key === key) current.current = null
  }, [])

  return { forAction, complete }
}
