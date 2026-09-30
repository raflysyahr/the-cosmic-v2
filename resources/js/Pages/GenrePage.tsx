import { useState } from 'react'
import { useSeries } from '../hooks/useSeries'
import { useGenres } from '../hooks/useGenres'
import ComicGrid from '../Components/comic/ComicGrid'
import Pagination from '../Components/comic/Pagination'
import Skeleton from '../Components/ui/Skeleton'
import Layout from '../Components/layout/Layout'

export default function GenrePage({ genre }: { genre: string }) {
  const [page, setPage] = useState(1)
  const { data: genres } = useGenres()
  const genreObj = genres.find((g) => g.slug === genre || g.name.toLowerCase().replace(/\s+/g, '-') === genre)
  const { data, meta, loading } = useSeries({ take: 20, page, genreId: genreObj?.id })

  return (
    <Layout>
      <div className="mx-auto max-w-7xl px-4 py-6">
        <h1 className="mb-6 text-lg font-bold text-white">
          Genre: <span className="text-[#999]">{genreObj?.name || genre}</span>
        </h1>

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
            {data.length === 0 ? (
              <p className="text-xs text-[#555]">No comics found for this genre.</p>
            ) : (
              <ComicGrid items={data} />
            )}
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
