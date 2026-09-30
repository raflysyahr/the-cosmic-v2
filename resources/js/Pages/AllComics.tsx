import { useState, useEffect, useRef } from 'react'
import { useSeries } from '../hooks/useSeries'
import { useGenres } from '../hooks/useGenres'
import ComicGrid from '../Components/comic/ComicGrid'
import Pagination from '../Components/comic/Pagination'
import Skeleton from '../Components/ui/Skeleton'
import { ChevronDown } from 'lucide-react'
import Layout from '../Components/layout/Layout'

export default function AllComics() {
  const [searchParams] = useState(() => new URL(window.location.href).searchParams)
  const [page, setPage] = useState(1)
  const [format, setFormat] = useState(searchParams.get('type') || '')
  const [genreId, setGenreId] = useState<string | undefined>()
  const [genresOpen, setGenresOpen] = useState(false)
  const genresRef = useRef<HTMLDivElement>(null)
  const { data, meta, loading } = useSeries({ take: 20, page, format: format || undefined, genreId })
  const { data: genres } = useGenres()

  useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      if (genresRef.current && !genresRef.current.contains(e.target as Node)) {
        setGenresOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClick)
    return () => document.removeEventListener('mousedown', handleClick)
  }, [])

  const formats = [
    { value: '', label: 'All' },
    { value: 'manhua', label: 'Manhua' },
    { value: 'manhwa', label: 'Manhwa' },
    { value: 'manga', label: 'Manga' }
  ]

  const selectedGenre = genreId ? genres.find((g) => g.id === genreId) : null

  return (
    <Layout>
      <div className="mx-auto max-w-7xl px-4 py-6">
        <h1 className="mb-6 text-lg font-bold text-white">
          {searchParams.get('type') ? searchParams.get('type')!.charAt(0).toUpperCase() + searchParams.get('type')!.slice(1) : 'All'} Comics
        </h1>

        <div className="mb-4 flex items-center gap-2">
          <span className="text-xs font-semibold text-[#555] uppercase tracking-wider">Format:</span>
          <div className="flex">
            {formats.map((f) => (
              <button
                key={f.value}
                onClick={() => { setFormat(f.value); setPage(1) }}
                className={`px-3 py-1.5 text-[10px] font-bold transition-colors ${
                  format === f.value
                    ? 'bg-white text-black'
                    : 'border border-[#2A2A2A] bg-[#111] text-[#555] hover:border-white/30 hover:text-white'
                }`}
              >
                {f.label}
              </button>
            ))}
          </div>
        </div>

        <div className="mb-6 relative" ref={genresRef}>
          <div className="flex items-center gap-2">
            <span className="text-xs font-semibold text-[#555] uppercase tracking-wider">Genre:</span>
            <button
              onClick={() => setGenresOpen(!genresOpen)}
              className="inline-flex items-center gap-1 border border-[#2A2A2A] bg-[#111] px-3 py-1.5 text-xs font-medium text-[#777] transition-colors hover:border-white/30 hover:text-white"
            >
              {selectedGenre ? selectedGenre.name : 'All'}
              <ChevronDown className={`h-3 w-3 transition-colors ${genresOpen ? 'rotate-180' : ''}`} />
            </button>
          </div>

          {genresOpen && (
            <div className="absolute left-0 top-full z-20 mt-1 flex w-72 max-h-60 overflow-y-auto flex-wrap gap-1 border border-[#2A2A2A] bg-black p-3 shadow-xl shadow-black/50">
              <button
                onClick={() => { setGenreId(undefined); setPage(1); setGenresOpen(false) }}
                className={`px-2.5 py-1 text-xs font-bold transition-colors ${
                  !genreId
                    ? 'bg-white text-black'
                    : 'border border-[#2A2A2A] text-[#555] hover:border-white/30 hover:text-white'
                }`}
              >
                All
              </button>
              {genres.map((g) => (
                <button
                  key={g.id}
                  onClick={() => { setGenreId(g.id); setPage(1); setGenresOpen(false) }}
                  className={`px-2.5 py-1 text-xs font-bold transition-colors ${
                    genreId === g.id
                      ? 'bg-white text-black'
                      : 'border border-[#2A2A2A] text-[#555] hover:border-white/30 hover:text-white'
                  }`}
                >
                  {g.name}
                </button>
              ))}
            </div>
          )}
        </div>

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
