export interface LatestChapter {
  index: number
  title: string
  releasedAt: string
}

export interface SeriesItem {
  id: string
  title: string
  slug: string
  cover: string
  rating: number
  status: string
  type: string
  isHot: boolean
  totalChapters: number
  genreIds?: string[]
  latestChapter?: LatestChapter
  chapters?:{
      chapterIndex: number;
      data: { title?: string; index?: number };
      createdAt?: string;
  }[]
}

export interface SeriesDetail {
  id: string
  title: string
  slug: string
  cover: string
  rating: number
  status: string
  type: string
  isHot: boolean
  totalChapters: number
  genreIds?: string[]
  nativeTitle: string
  author: string
  genres: Genre[]
  animeAdaptation: boolean
  synopsis: string
  releasedAt: string
  views: number
  chapters: ChapterItem[]
}

export interface ChapterItem {
  index: number
  title: string
  releasedAt: string
}

export interface ChapterPages {
  images: string[]
}

export interface Genre {
  id: string
  name: string
  slug?: string
}

export interface PaginationMeta {
  total: number
  page: number
  lastPage: number
}

export interface ApiResponse<T> {
  success: boolean
  data: T
  meta?: PaginationMeta
}
