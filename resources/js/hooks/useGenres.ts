import { useState, useEffect } from 'react'
import { fetchGenres } from '../api/genres'
import type { Genre } from '../types'

export function useGenres(): { data: Genre[]; loading: boolean } {
  const [data, setData] = useState<Genre[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchGenres()
      .then(setData)
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [])

  return { data, loading }
}
