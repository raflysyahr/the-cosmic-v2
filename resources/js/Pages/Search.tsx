import { useState, useEffect } from 'react'
import { fetchSearch } from '../api/series'
import type { SeriesItem } from '../types'
import type { PaginationMeta } from '../types'
import ComicGrid from '../Components/comic/ComicGrid'
import Pagination from '../Components/comic/Pagination'
import Skeleton from '../Components/ui/Skeleton'
import { Search } from 'lucide-react'
import Layout from '../Components/layout/Layout'

export default function SearchPage() {
  const [searchParams] = useState(() => new URL(window.location.href).searchParams)
  const q = searchParams.get('q') || ''
  const [results, setResults] = useState<SeriesItem[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    setPage(1)
  }, [q])

  useEffect(() => {
    if (!q.trim()) {
      setResults([])
      setMeta(null)
      return
    }
    setLoading(true)
    fetchSearch(q, page)
      .then((res) => {
        setResults(res.data)
        setMeta(res.meta)
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [q, page])

  return (
    <Layout>
      <div key={`${q}-${page}`} className="mx-auto max-w-7xl px-4 py-6">
        <h1 className="mb-6 text-lg font-bold text-white">
          Search results for: <span className="text-[#555]">&quot;{q}&quot;</span>
        </h1>

        {loading ? (
          <div className="grid grid-cols-2 gap-px bg-[#2A2A2A] sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            {Array.from({ length: 10 }).map((_, i) => (
              <div key={i} className="bg-[#111]">
                <Skeleton className="aspect-[720/1013]" />
                <div className="p-3 space-y-2">
                  <Skeleton className="h-4 w-full" />
                  <Skeleton className="h-3 w-2/3" />
                </div>
              </div>
            ))}
          </div>
        ) : results.length === 0 ? (
          q.trim() ? (
            <div className="py-10 text-center flex flex-col justify-center overflow-hidden">
              <div style={{ display: 'flex', alignItems: 'center', width: '100%', padding: '0 24px' }}>
                <div style={{ position: 'relative', fontFamily: "'BitcountGridDouble'", fontSize: 20, color: '#fff' }}>
                  <span style={{ position: 'relative', zIndex: 10 }}>The Cosmic</span>
                  <img
                    src="/black-hole.jpg"
                    alt=""
                    style={{ position: 'absolute', left: '90%', top: '-5px', width: 62, transform: 'rotate(30deg)' }}
                  />
                </div>
              </div>
              <div
                style={{
                  position: 'relative',
                  width: 800,
                  maxWidth: '120vw',
                  margin: '0 auto 32px',
                  transform: 'translateX(0px)',
                }}
              >
                <img
                  src="/lv_0_20260622193401.jpg"
                  alt="No results"
                  style={{ width: '100%', display: 'block' }}
                />
                <div
                  style={{
                    position: 'absolute',
                    inset: 0,
                    pointerEvents: 'none',
                    background:
                      'linear-gradient(to bottom, #000 0%, transparent 20%, transparent 80%, #000 100%), linear-gradient(to right, #000 0%, transparent 10%, transparent 90%, #000 100%)',
                  }}
                />
              </div>
            </div>
          ) : (
            <div className="py-20 text-center">
              <Search className="mx-auto mb-4 h-8 w-8 text-[#333]" />
              <p className="text-xs text-[#555]">Enter a search term to find comics.</p>
            </div>
          )
        ) : (
          <>
            <ComicGrid items={results} />
            {meta && meta.lastPage > 1 && (
              <Pagination page={page} lastPage={meta.lastPage} onPageChange={setPage} />
            )}
          </>
        )}
      </div>
    </Layout>
  )
}
