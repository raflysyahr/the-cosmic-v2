import client from './client'
import type { ApiResponse, SeriesItem, SeriesDetail, ChapterItem, ChapterPages, PaginationMeta } from '../types'

export async function fetchSeries(params: {
  take?: number
  page?: number
  format?: string
  sort?: string
  preset?: string
  takeChapter?: number
}): Promise<{ data: SeriesItem[]; meta: PaginationMeta }> {
  const res = await client.get<ApiResponse<SeriesItem[]>>('/v1/series', { params })
  return { data: res.data.data, meta: res.data.meta! }
}

export async function fetchSeriesBySlug(slug: string): Promise<SeriesDetail> {
  const res = await client.get<ApiResponse<SeriesDetail>>(`/v1/series/${slug}`)
  return res.data.data
}

export async function fetchSearch(q: string, page = 1, take = 12): Promise<{ data: SeriesItem[]; meta: PaginationMeta }> {
  const res = await client.get<ApiResponse<SeriesItem[]>>('/v1/series/search', {
    params: { q, page, take },
  })
  return { data: res.data.data, meta: res.data.meta! }
}

export async function fetchTrending(): Promise<SeriesItem[]> {
  const res = await client.get<ApiResponse<SeriesItem[]>>('/v1/series/trending')
  return res.data.data
}

export async function fetchChapters(slug: string): Promise<ChapterItem[]> {
  const res = await client.get<ApiResponse<ChapterItem[]>>(`/v1/series/${slug}/chapters`)
  return res.data.data || []
}

export async function fetchChapterPages(slug: string, index: number): Promise<ChapterPages> {
  const res = await client.get<ApiResponse<ChapterPages>>(`/v1/series/${slug}/chapters/${index}`)
  return res.data.data
}
