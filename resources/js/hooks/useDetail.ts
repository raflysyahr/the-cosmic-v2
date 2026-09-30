import { useState, useEffect } from 'react'
import { fetchSeriesBySlug } from '../api/series'
import type { SeriesDetail, ChapterItem } from '../types'

interface UseDetailResult {
  series: SeriesDetail | null
  chapters: ChapterItem[]
  loading: boolean
  error: string | null
}

export function useDetail(slug: string): UseDetailResult {
  const [series, setSeries] = useState<SeriesDetail | null>(null)
  const [chapters, setChapters] = useState<ChapterItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!slug) return
    let cancelled = false
    setLoading(true)
    setError(null)

    fetchSeriesBySlug(slug)
      .then((s) => {
        if (!cancelled) {
          setSeries(s)
          setChapters(s.chapters || [])
        }
      })
      .catch((err) => {
        if (!cancelled) setError(err.message)
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => { cancelled = true }
  }, [slug])

  return { series, chapters, loading, error }
}
