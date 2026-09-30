import { Link } from '@inertiajs/react'
import { useBookmarks } from '../hooks/useBookmarks'
import { useAuth } from '../contexts/AuthContext'
import { Trash2, Bookmark } from 'lucide-react'
import TypeBadge from '../Components/comic/TypeBadge'
import Layout from '../Components/layout/Layout'

export default function Bookmarks() {
  const { user } = useAuth()
  const userId = user?.id
  const { items, remove } = useBookmarks(userId)

  return (
    <Layout>
      <div className="mx-auto max-w-7xl px-4 py-6">
        <h2 className="mb-4 text-xs font-bold tracking-wider uppercase text-[#555]">
          Bookmarks {items.length > 0 && <span className="font-normal">({items.length})</span>}
        </h2>

        {items.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20">
            <Bookmark className="mb-3 h-8 w-8 text-[#333]" />
            <p className="text-sm text-[#555]">No bookmarks yet.</p>
            <Link
              href="/"
              className="mt-3 text-xs text-[#555] transition-colors hover:text-white"
            >
              Browse comics
            </Link>
          </div>
        ) : (
          <div className="grid grid-cols-2 gap-px bg-[#2A2A2A] sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            {items.map((item) => (
              <div
                key={item.slug}
                className="group relative flex flex-col border border-[#2A2A2A] bg-[#111] transition-colors hover:border-white/20"
              >
                <Link
                  href={`/series/${item.slug}`}
                  className="flex flex-col"
                >
                  <div className="relative aspect-[720/1013] overflow-hidden bg-[#1A1A1A]">
                    {item.coverImage ? (
                      <img
                        src={item.coverImage}
                        alt={item.title}
                        loading="lazy"
                        className="h-full w-full object-cover transition-opacity duration-300 group-hover:opacity-80"
                      />
                    ) : (
                      <div className="flex h-full items-center justify-center text-xs text-[#555]">No Cover</div>
                    )}
                    <div className="absolute right-1.5 top-1.5">
                      <TypeBadge format={item.format} />
                    </div>
                  </div>

                  <div className="flex flex-1 flex-col p-3">
                    <h3
                      className="line-clamp-2 text-sm font-bold leading-tight text-white transition-colors group-hover:text-[#999]"
                      title={item.title}
                    >
                      {item.title}
                    </h3>
                  </div>
                </Link>

                <button
                  onClick={() => remove(item.slug)}
                  className="absolute right-1.5 bottom-1.5 flex h-7 w-7 items-center justify-center bg-black/60 text-[#555] opacity-0 transition-all hover:text-white group-hover:opacity-100"
                  title="Remove bookmark"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            ))}
          </div>
        )}
      </div>
    </Layout>
  )
}
