import { useState, useMemo, useEffect } from 'react'
import { Link } from '@inertiajs/react'
import { useDetail } from '../hooks/useDetail'
import { useGenres } from '../hooks/useGenres'
import Layout from '../Components/layout/Layout'
import Rating from '../Components/ui/Rating'
import Badge from '../Components/ui/Badge'
import TypeBadge from '../Components/comic/TypeBadge'
import GenreTag from '../Components/ui/GenreTag'
import Skeleton from '../Components/ui/Skeleton'
import { Eye, Calendar, BookOpen, ArrowLeft, Bookmark, BookmarkCheck, MessageSquare, Search, ArrowUpDown } from 'lucide-react'
import { formatViews, formatTimeAgo } from '../utils'
import { useBookmarks } from '../hooks/useBookmarks'
import { useReadingHistory } from '../hooks/useReadingHistory'
import { usePopup } from '../contexts/PopupContext'
import { useAuth } from '../contexts/AuthContext'

export default function Detail({ slug }: { slug: string }) {
  const { series, chapters, loading, error } = useDetail(slug || '')
  const { data: genres } = useGenres()
  const [expanded, setExpanded] = useState(false)
  const { user } = useAuth()
  const userId = user?.id
  const { isBookmarked, toggle } = useBookmarks(userId)
  const { getReadSet, loadForSlug } = useReadingHistory(userId)
  const { showPopup } = usePopup()
  const [searchQuery, setSearchQuery] = useState('')
  const [sortOrder, setSortOrder] = useState<'newest' | 'oldest'>('newest')

  useEffect(() => {
    if (userId && series?.slug) {
      loadForSlug(series.slug)
    }
  }, [userId, series?.slug])

  const displayedChapters = useMemo(() => {
    let filtered = chapters
    if (searchQuery.trim()) {
      const q = searchQuery.trim().toLowerCase()
      filtered = chapters.filter((ch) => {
        if (/^\d+$/.test(q)) {
          return String(ch.index).includes(q) || ch.title.toLowerCase().includes(q)
        }
        return ch.title.toLowerCase().includes(q)
      })
    }
    if (sortOrder === 'oldest') {
      return [...filtered].reverse()
    }
    return filtered
  }, [chapters, searchQuery, sortOrder])

  if (loading) {
    return (
      <Layout>
        <div className="mx-auto max-w-7xl px-4 py-6">
          <Skeleton className="mb-5 h-3 w-12" />
          <div className="relative mb-8 overflow-hidden">
            <div className="relative flex justify-center py-10 bg-[#0a0a0a]">
              <div className="absolute inset-0 bg-[#111]" />
              <div className="absolute inset-0 bg-gradient-to-t from-black to-transparent" />
              <Skeleton className="relative z-10 w-[150px] aspect-[720/1013]" />
            </div>
          </div>
          <div className="mb-8">
            <Skeleton className="mb-2 h-6 w-3/4" />
            <Skeleton className="mb-4 h-3 w-1/3" />
            <div className="mb-5 flex items-center gap-3">
              <Skeleton className="h-5 w-14" />
              <Skeleton className="h-5 w-16" />
              <Skeleton className="h-5 w-20" />
            </div>
            <div className="mb-5 flex gap-6">
              <Skeleton className="h-3 w-28" />
              <Skeleton className="h-3 w-24" />
              <Skeleton className="h-3 w-20" />
              <Skeleton className="h-3 w-32" />
            </div>
            <div className="mb-5 flex gap-1.5">
              <Skeleton className="h-4 w-16" />
              <Skeleton className="h-4 w-14" />
              <Skeleton className="h-4 w-20" />
            </div>
          </div>
          <div className="mb-8">
            <Skeleton className="mb-3 h-3 w-24" />
            <div className="border border-[#2A2A2A] bg-[#111] p-4">
              <Skeleton className="mb-2 h-3 w-full" />
              <Skeleton className="mb-2 h-3 w-5/6" />
              <Skeleton className="h-3 w-2/3" />
            </div>
          </div>
          <div className="mb-6">
            <Skeleton className="mb-3 h-3 w-28" />
            <div className="grid gap-px border border-[#2A2A2A] bg-[#2A2A2A] sm:grid-cols-2 lg:grid-cols-3">
              {Array.from({ length: 9 }).map((_, i) => (
                <div key={i} className="bg-[#111] px-4 py-2.5">
                  <Skeleton className="h-4 w-40" />
                </div>
              ))}
            </div>
          </div>
        </div>
      </Layout>
    )
  }

  if (error) {
    return (
      <Layout>
        <div className="mx-auto max-w-7xl px-4 py-20 text-center">
          <p className="text-sm text-[#555]">{error}</p>
          <Link href="/" className="mt-4 inline-flex items-center gap-1.5 text-xs text-[#777] hover:text-white transition-colors">
            <ArrowLeft className="h-3 w-3" /> Back to Home
          </Link>
        </div>
      </Layout>
    )
  }

  if (!series) return <Layout><div className="mx-auto max-w-7xl px-4 py-6" /></Layout>

  const genreNames = (series.genreIds || [])
    .map((id) => genres.find((g) => g.id === id)?.name)
    .filter(Boolean) as string[]

  return (
    <Layout>
      <div className="mx-auto max-w-7xl px-4 py-6">
        <Link
          href="/"
          className="mb-5 inline-flex items-center gap-1.5 text-xs text-[#555] transition-colors hover:text-white"
        >
          <ArrowLeft className="h-3 w-3" /> Back
        </Link>

        {/* Hero Cover Section */}
        <div className="relative mb-8 overflow-hidden">
          {series.cover && (
            <div
              className="relative flex justify-center py-10 h-[400px]  bg-cover bg-center"
              style={{ backgroundImage: `url(${series.cover})` }}
            >
              <div
                className="absolute inset-0 scale-110 bg-cover bg-center blur-2xl opacity-30"
style={{ backgroundImage: `url(${series.cover})` }}
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black to-transparent" />
              <div className="relative z-10 w-[150px]">
                <img
                  src={series.cover}
                  alt={series.title}
                  className="w-full object-cover"
                />
              </div>
            </div>
          )}
        </div>

        {/* Info */}
        <div className="mb-8">
          <h1 className="mb-1 text-xl font-bold leading-tight text-white">{series.title}</h1>
          {series.nativeTitle && (
            <p className="mb-4 text-xs text-[#555]">{series.nativeTitle}</p>
          )}

          <div className="mb-5 flex flex-wrap items-center gap-3">
            <TypeBadge format={series.type} />
            {series.status && (
              <Badge variant={series.status === 'ongoing' ? 'ongoing' : 'completed'}>
                {series.status}
              </Badge>
            )}
            <Rating value={series.rating} />
            <button
              onClick={() => {
                const wasBookmarked = isBookmarked(series.slug)
                toggle({ slug: series.slug, title: series.title, coverImage: series.cover, format: series.type })
                if (!wasBookmarked) {
                  showPopup({
                    type: 'notification',
                    title: 'Bookmarked',
                    message: `"${series.title}" has been added to your bookmarks.`,
                  })
                }
              }}
              className={`flex items-center gap-1 text-xs font-bold transition-colors ${
                isBookmarked(series.slug)
                  ? 'text-white'
                  : 'text-[#555] hover:text-white'
              }`}
              title={isBookmarked(series.slug) ? 'Remove bookmark' : 'Add bookmark'}
            >
              {isBookmarked(series.slug) ? <BookmarkCheck className="h-3.5 w-3.5" /> : <Bookmark className="h-3.5 w-3.5" />}
            </button>
            {user?.role === 'admin' && (
              <Link
                href={`/discuss/comic-${series.slug}?title=${encodeURIComponent(series.title)}`}
                className="flex items-center gap-1 text-xs font-bold text-[#555] transition-colors hover:text-white"
              >
                <MessageSquare className="h-3.5 w-3.5" /> DISCUSS
              </Link>
            )}
          </div>

          <div className="mb-5 flex flex-wrap gap-x-6 gap-y-1.5 text-xs">
            {series.author && (
              <div className="flex items-center gap-1.5 text-[#999]">
                <span className="font-semibold text-[#555]">Author:</span> {series.author}
              </div>
            )}
            {series.releasedAt && (
              <div className="flex items-center gap-1.5 text-[#999]">
                <Calendar className="h-3 w-3 text-[#555]" /> {series.releasedAt}
              </div>
            )}
            {series.views !== undefined && (
              <div className="flex items-center gap-1.5 text-[#999]">
                <Eye className="h-3 w-3 text-[#555]" /> {formatViews(series.views)}
              </div>
            )}
            {series.totalChapters && (
              <div className="flex items-center gap-1.5 text-[#999]">
                <BookOpen className="h-3 w-3 text-[#555]" /> {series.totalChapters} chapters
              </div>
            )}
          </div>

          {series.animeAdaptation && (
            <div className="mb-5 inline-flex items-center gap-1.5 border border-white/20 bg-white/5 px-2 py-1 text-[10px] font-bold text-white">
              Anime Adaptation Available
            </div>
          )}

          {genreNames.length > 0 && (
            <div className="mb-5 flex flex-wrap gap-1.5">
              {genreNames.map((name) => (
                <GenreTag key={name} name={name} />
              ))}
            </div>
          )}
        </div>

        {/* Description */}
        <div className="mb-8">
          <h2 className="mb-3 text-xs font-bold tracking-wider uppercase text-[#555]">Description</h2>
          <div className=" p-4">
            <p className={`text-sm leading-relaxed text-[#777] ${!expanded ? 'line-clamp-3' : ''}`}>
              {series.synopsis || 'No description available.'}
            </p>
          </div>
          {series.synopsis && series.synopsis.length > 150 && (
            <button
              onClick={() => setExpanded(!expanded)}
              className="mt-1 text-xs text-[#555] transition-colors hover:text-white"
            >
              {expanded ? 'See Less' : 'See More'}
            </button>
          )}
        </div>

        {/* Chapter List */}
        <div className="mb-6">
          <h2 className="mb-2 text-xs font-bold tracking-wider uppercase text-[#555]">
            Chapters <span className="text-[#555] font-normal">({chapters.length})</span>
          </h2>

          <div className="mb-3 flex items-center gap-2">
            <div className="relative flex-1">
              <Search className="absolute left-2.5 top-1/2 h-3 w-3 -translate-y-1/2 text-[#555]" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search chapter..."
                className="h-8 w-full border border-[#2A2A2A] bg-[#111] pl-7 pr-2 text-xs text-white outline-none placeholder:text-[#555] transition-colors focus:border-white/30"
              />
            </div>
            <button
              onClick={() => setSortOrder((o) => (o === 'newest' ? 'oldest' : 'newest'))}
              className="flex h-8 shrink-0 items-center gap-1.5  border border-[#2A2A2A] bg-[#111] px-2.5 text-[10px] font-bold text-[#555] transition-colors hover:border-white/30 hover:text-white"
              title={`Sort: ${sortOrder === 'newest' ? 'Newest first' : 'Oldest first'}`}
            >
              <ArrowUpDown className="h-3 w-3" />
              {sortOrder === 'newest' ? 'Newest' : 'Oldest'}
            </button>
          </div>

          {displayedChapters.length === 0 ? (
            <p className="text-xs text-[#555]">
              {searchQuery.trim() ? 'No chapters match your search.' : 'No chapters available yet.'}
            </p>
          ) : (
            <div className="grid gap-px border border-[#2A2A2A] bg-[#2A2A2A] sm:grid-cols-2 lg:grid-cols-3">
              {(() => {
                const readSet = getReadSet(series.slug)
                return displayedChapters.map((ch) => {
                  const isRead = readSet.has(ch.index)
                  return (
                    <Link
                      key={`${ch.index}-${ch.releasedAt}`}
                      href={`/series/${series.slug}/chapter/${ch.index}`}
                      className="group flex items-center justify-between bg-[#111] px-4 py-2.5 transition-colors hover:bg-[#1A1A1A]"
                    >
                      <span
                        className={`text-sm font-medium transition-colors group-hover:text-white ${
                          isRead ? 'text-white' : 'text-[#777]'
                        }`}
                      >
                        {ch.title || `Chapter ${ch.index}`}
                      </span>
                      {ch.releasedAt && (
                        <span className="shrink-0 text-[10px] text-[#555]">
                          {formatTimeAgo(ch.releasedAt)}
                        </span>
                      )}
                    </Link>
                  )
                })
              })()}
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}
