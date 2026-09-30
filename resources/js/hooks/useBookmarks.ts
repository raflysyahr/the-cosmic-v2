import { useState, useCallback, useEffect } from 'react'
import client from '../api/client'

const STORAGE_KEY = 'cosmic_bookmarks'

export interface BookmarkItem {
  slug: string
  title: string
  coverImage?: string
  format: string
  addedAt: string
}

interface ServerBookmark {
  slug: string
  title: string
  cover_image: string | null
  format: string | null
  created_at: string
}

function loadLocal(): BookmarkItem[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : []
  } catch {
    return []
  }
}

function saveLocal(items: BookmarkItem[]): void {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(items))
}

function serverToItem(s: ServerBookmark): BookmarkItem {
  return {
    slug: s.slug,
    title: s.title,
    coverImage: s.cover_image ?? undefined,
    format: s.format ?? '',
    addedAt: s.created_at,
  }
}

export function useBookmarks(userId?: string | null) {
  const [items, setItems] = useState<BookmarkItem[]>([])

  useEffect(() => {
    if (userId) {
      client.get('/bookmarks')
        .then((res) => {
          const data = res.data as { data?: ServerBookmark[] }
          const serverItems = (data.data ?? []).map(serverToItem)
          setItems(serverItems)
        })
        .catch(() => {
          setItems(loadLocal())
        })
    } else {
      setItems(loadLocal())
    }
  }, [userId])

  const isBookmarked = useCallback(
    (slug: string) => items.some((b) => b.slug === slug),
    [items]
  )

  const add = useCallback(
    (item: Omit<BookmarkItem, 'addedAt'>) => {
      if (userId) {
        client.post('/bookmarks', {
          slug: item.slug,
          title: item.title,
          cover_image: item.coverImage ?? null,
          format: item.format,
        }).then((res) => {
          const data = res.data as { data?: ServerBookmark }
          if (data.data) {
            setItems((prev) => {
              if (prev.some((b) => b.slug === data.data!.slug)) return prev
              return [serverToItem(data.data!), ...prev]
            })
          }
        })
      } else {
        const all = loadLocal()
        if (!all.some((b) => b.slug === item.slug)) {
          all.unshift({ ...item, addedAt: new Date().toISOString() })
          saveLocal(all)
          setItems(all)
        }
      }
    },
    [userId]
  )

  const remove = useCallback((slug: string) => {
    if (userId) {
      client.delete(`/bookmarks/${encodeURIComponent(slug)}`)
        .then(() => {
          setItems((prev) => prev.filter((b) => b.slug !== slug))
        })
    } else {
      const all = loadLocal().filter((b) => b.slug !== slug)
      saveLocal(all)
      setItems(all)
    }
  }, [userId])

  const toggle = useCallback(
    (item: Omit<BookmarkItem, 'addedAt'>) => {
      if (items.some((b) => b.slug === item.slug)) {
        remove(item.slug)
      } else {
        add(item)
      }
    },
    [items, add, remove]
  )

  return { items, isBookmarked, add, remove, toggle }
}
