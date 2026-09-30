import { useState, useEffect, useRef, useCallback } from 'react'
import client from '../api/client'

const STORAGE_KEY = 'cosmic_reading_history'

export type ReadingHistory = Record<string, number[]>

function loadLocal(): ReadingHistory {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch {
    return {}
  }
}

function saveLocal(data: ReadingHistory) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(data))
}

export function useReadingHistory(userId?: string | null) {
  const cache = useRef<ReadingHistory>(loadLocal())
  const [version, setVersion] = useState(0)

  useEffect(() => {
    if (userId) {
      // Guest mode: keep localStorage listener
      const handler = () => {
        cache.current = loadLocal()
        setVersion((v) => v + 1)
      }
      window.addEventListener('storage', handler)
      return () => window.removeEventListener('storage', handler)
    }
  }, [userId])

  const getReadSet = useCallback(
    (slug: string): Set<number> => new Set(cache.current[slug] || []),
    [version]
  )

  const loadForSlug = useCallback(async (slug: string) => {
    try {
      const res = await client.get(`/reading-history/${encodeURIComponent(slug)}`)
      const data = res.data as { read_chapters?: number[] }
      const indices: number[] = data.read_chapters ?? []
      cache.current = { ...cache.current, [slug]: indices }
      setVersion((v) => v + 1)
      return new Set(indices)
    } catch {
      // fallback to cache
    }
    return new Set(cache.current[slug] || [])
  }, [])

  const markRead = useCallback((slug: string, index: number, meta?: {
    title?: string
    coverImage?: string
    chapterTitle?: string
    chapterUrl?: string
  }) => {
    if (userId) {
      client.post('/reading-history', {
        slug,
        title: meta?.title ?? '',
        cover_image: meta?.coverImage ?? null,
        chapter_index: index,
        chapter_title: meta?.chapterTitle ?? null,
        chapter_url: meta?.chapterUrl ?? null,
      }).then(() => {
        const data = cache.current[slug] || []
        if (!data.includes(index)) {
          data.push(index)
          cache.current = { ...cache.current, [slug]: data }
          setVersion((v) => v + 1)
        }
      })
    } else {
      const data = loadLocal()
      const list = data[slug] || []
      if (!list.includes(index)) {
        list.push(index)
        data[slug] = list
        saveLocal(data)
        cache.current = data
        setVersion((v) => v + 1)
      }
    }
  }, [userId])

  return { getReadSet, markRead, loadForSlug }
}
