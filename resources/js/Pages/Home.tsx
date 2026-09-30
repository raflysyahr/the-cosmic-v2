import { useState, useEffect } from 'react'
import { useSeries, useTrending } from '../hooks/useSeries'
import { getRichHistory, saveRichHistory } from '../hooks/useReadingHistoryRich'
import  client  from '../api/client'
import type { HistoryEntry } from '../hooks/useReadingHistoryRich'
import { Link } from '@inertiajs/react'
import Layout from '../Components/layout/Layout'
import ComicGrid from '../Components/comic/ComicGrid'
import Pagination from '../Components/comic/Pagination'
import Skeleton from '../Components/ui/Skeleton'
import { formatTimeAgo } from '../utils'
import { usePopup } from '../contexts/PopupContext'
import { useAuth } from '../contexts/AuthContext'

export default function Home() {
  const { user, loading: authLoading } = useAuth()
  const isLoggedIn = !!user

  const [page, setPage] = useState(1)
  const [history, setHistory] = useState<HistoryEntry[]>([])
  const { data, meta, loading } = useSeries({ take: 30, page, preset: 'rilisan_terbaru', takeChapter: 200 })
  const { data: trending, loading: trendingLoading } = useTrending()
  const { showPopup } = usePopup()

  useEffect(() => {

    if (authLoading) return
    if (isLoggedIn) {
      client.get('/reading-history/recent')
        .then((res) => {
          const entries: HistoryEntry[] = res.data.data ?? []
          entries.forEach((e) => saveRichHistory(e))
          setHistory(entries)

        })
        .catch(() => {
          setHistory(getRichHistory())

        })
    } else {
      setHistory(getRichHistory())
    }
  }, [isLoggedIn, authLoading])

  useEffect(() => {
    const visited = localStorage.getItem('cosmic_first_visit')
    if (!visited) {
      const timer = setTimeout(() => {
        showPopup({
          type: 'info',
          title: 'Welcome to The Cosmic',
          message: 'Your gateway to explore comics, manhwa, manga, and manhua. Happy reading!',
          confirmText: 'Let\'s Go',
        })
        localStorage.setItem('cosmic_first_visit', 'true')
      }, 1000)
      return () => clearTimeout(timer)
    }
  }, [showPopup])

  return (
    <Layout>
      <div className="mx-auto max-w-7xl px-4 py-6">
        {/* Trending Banner */}
        {trendingLoading ? (
          <Skeleton className="mb-8 h-64 w-full" />
        ) : trending.length > 0 && (
          <div className="mb-8">
            <h2 className="mb-4 text-xs font-bold tracking-wider uppercase text-[#555]">
              Trending Now
            </h2>
            <div className="flex gap-2 overflow-x-auto pb-2 scrollbar-none">
              {trending.slice(0, 8).map((item) => (
                <a
                  key={item.id}
                  href={`/series/${item.slug}`}
                  className="group shrink-0 w-[150px]"
                >
                  <div className="aspect-[720/1013] overflow-hidden bg-[#1A1A1A]">
                    {item.cover && (
                      <img
                        src={item.cover}
                        alt={item.title}
                        className="h-full w-full object-cover transition-opacity duration-300 group-hover:opacity-80"
                      />
                    )}
                  </div>
                </a>
              ))}
            </div>
          </div>
        )}

        {/* Reading History */}
        {history.length > 0 && (
          <div className="mb-8">
            <h2 className="mb-4 text-xs font-bold tracking-wider uppercase text-[#555]">
              Continue Reading
            </h2>
            <div className="flex gap-2 overflow-x-auto pb-2 scrollbar-none">
              {history.map((entry) => (
                <Link
                  key={entry.slug}
                  href={entry.chapterUrl}
                  className="group w-[150px] shrink-0"
                >
                  <div className="flex h-full flex-col border border-[#2A2A2A] bg-[#111] transition-colors hover:border-white/20">
                    <div className="relative aspect-[720/1013] overflow-hidden bg-[#1A1A1A]">
                      {entry.coverImage ? (
                        <img
                          src={entry.coverImage}
                          alt={entry.title}
                          loading="lazy"
                          className="h-full w-full object-cover transition-opacity duration-300 group-hover:opacity-80"
                        />
                      ) : (
                        <div className="flex h-full items-center justify-center text-xs text-[#555]">No Cover</div>
                      )}
                    </div>
                    <div className="flex flex-1 flex-col p-3">
                      <h3
                        className="mb-1 line-clamp-2 text-sm font-bold leading-tight text-white transition-colors group-hover:text-[#999]"
                        title={entry.title}
                      >
                        {entry.title}
                      </h3>
                      <div className="mt-auto flex flex-col gap-px">
                        <div className="border-t border-[#2A2A2A] px-1 py-1.5">
                          <span className="block truncate text-[11px] font-medium text-[#777]">
                            {entry.chapterTitle}
                          </span>
                          <span className="block text-[10px] text-[#555]">
                            {formatTimeAgo(new Date(entry.timestamp).toISOString())}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          </div>
        )}

        {/* Series Grid */}
        <h2 className="mb-4 text-xs font-bold tracking-wider uppercase text-[#555]">
          Latest Updates
        </h2>

        {loading ? (
          <div className="grid grid-cols-2 gap-px bg-[#2A2A2A] sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            {Array.from({ length: 20 }).map((_, i) => (
              <div key={i} className="bg-[#111]">
                <Skeleton className="aspect-[720/1013]" />
                <div className="p-3 space-y-2">
                  <Skeleton className="h-4 w-full" />
                  <Skeleton className="h-3 w-2/3" />
                </div>
              </div>
            ))}
          </div>
        ) : (
          <>
            <ComicGrid items={data} />
            {meta && (
              <Pagination
                page={page}
                lastPage={meta.lastPage}
                onPageChange={setPage}
              />
            )}
          </>
        )}
      </div>
    </Layout>
  )
}
