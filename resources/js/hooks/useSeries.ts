import { useState, useEffect, useRef } from 'react'
import { fetchSeries, fetchTrending } from '../api/series'
import type { SeriesItem, PaginationMeta } from '../types'

interface UseSeriesResult {
  data: SeriesItem[]
  meta: PaginationMeta | null
  loading: boolean
  error: string | null
}

export function useSeries(params: {
  take?: number
  page?: number
  format?: string
  genreId?: string
  sort?: string
  preset?: string
  takeChapter?: number
}): UseSeriesResult {
  const [data, setData] = useState<SeriesItem[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const cacheRef = useRef<Map<string, SeriesItem[]>>(new Map())
  const fetchIdRef = useRef(0)

  useEffect(() => {
    let cancelled = false
    const id = ++fetchIdRef.current
    const { take: rawTake, page = 1, format, genreId, sort, preset, takeChapter } = params
    const perPage = rawTake || 20

    if (genreId) {
      const cacheKey = `${genreId}:${format || ''}`
      const cached = cacheRef.current.get(cacheKey)

      if (cached) {
        // Cache hit — instant slice, no API call
        const total = cached.length
        const lastPage = Math.ceil(total / perPage)
        const start = (page - 1) * perPage
        setData(cached.slice(start, start + perPage))
        setMeta({ total, page, lastPage })
        setLoading(false)
        setError(null)
        return
      }

      // Cache miss — fetch 200 items from API
      setLoading(true)
      setError(null)
      fetchSeries({ take: 200, page: 1, format, sort, preset, takeChapter })
        .then((res) => {
          if (cancelled || id !== fetchIdRef.current) return
          const filtered = res.data.filter(
            (item) => item.genreIds?.includes(genreId)
          )
          cacheRef.current.set(cacheKey, filtered)
          const total = filtered.length
          const lastPage = Math.ceil(total / perPage)
          const start = (page - 1) * perPage
          setData(filtered.slice(start, start + perPage))
          setMeta({ total, page, lastPage })
          setLoading(false)
        })
        .catch((err) => {
          if (!cancelled && id === fetchIdRef.current) setError(err.message)
          if (!cancelled) setLoading(false)
        })
    } else {
      // Normal paginated fetch (no genre filter)
      setLoading(true)
      setError(null)
      fetchSeries({ take: perPage, page, format, sort, preset, takeChapter })
        .then((res) => {
          if (cancelled || id !== fetchIdRef.current) return
          setData(res.data)
          setMeta(res.meta)
          setLoading(false)
        })
        .catch((err) => {
          if (!cancelled && id === fetchIdRef.current) setError(err.message)
          if (!cancelled) setLoading(false)
        })
    }

    return () => { cancelled = true }
  }, [params.take, params.page, params.format, params.genreId, params.sort, params.preset, params.takeChapter])

  return { data, meta, loading, error }
}

interface UseTrendingResult {
  data: SeriesItem[]
  loading: boolean
}

export function useTrending(): UseTrendingResult {
  const [data, setData] = useState<SeriesItem[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchTrending()
      .then(setData)
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [])

  return { data, loading }
}
