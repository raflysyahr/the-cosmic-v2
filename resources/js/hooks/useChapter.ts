import { useState, useEffect } from 'react'
import { fetchChapterPages } from '../api/series'
import type { ChapterPages } from '../types'

interface UseChapterResult {
  chapter: ChapterPages | null
  images: string[]
  loading: boolean
  error: string | null
}

export function useChapter(slug: string, index: number): UseChapterResult {
  const [chapter, setChapter] = useState<ChapterPages | null>(null)
  const [images, setImages] = useState<string[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!slug || !index) return
    let cancelled = false
    setLoading(true)
    setError(null)

    fetchChapterPages(slug, index)
      .then((res) => {
        if (!cancelled) {
          setChapter(res)
          setImages(res.images || [])
        }
      })
      .catch((err) => {
        if (!cancelled) setError(err.message)
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => { cancelled = true }
  }, [slug, index])

  return { chapter, images, loading, error }
}
