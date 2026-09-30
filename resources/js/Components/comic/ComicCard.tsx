import { Link } from '@inertiajs/react'
import type { SeriesItem } from '../../types'
import Badge from '../ui/Badge'
import Rating from '../ui/Rating'
import TypeBadge from './TypeBadge'
import { fetchChapters } from '../../api/series.ts'

interface ComicCardProps {
    data: SeriesItem
}

const TWENTY_FOUR_HOURS = 24 * 60 * 60 * 1000

function isWithin24h(dateStr: string): boolean {
    return Date.now() - new Date(dateStr).getTime() < TWENTY_FOUR_HOURS
}

function badgeLabel(count: number): string {
    if (count <= 9) return `+${count}`
    if (count % 10 === 0) return `+${count}`
    return `${Math.floor(count / 10) * 10}+`
}

function formatTimeAgo(dateStr: string): string {
    const now = Date.now()
    const date = new Date(dateStr).getTime()
    const diff = Math.floor((now - date) / 1000)
    const units = [
        { label: 'year', seconds: 31536000 },
        { label: 'month', seconds: 2592000 },
        { label: 'week', seconds: 604800 },
        { label: 'day', seconds: 86400 },
        { label: 'hour', seconds: 3600 },
        { label: 'minute', seconds: 60 },
    ]
    for (const unit of units) {
        const val = Math.floor(diff / unit.seconds)
        if (val >= 1) return `${val} ${unit.label}${val > 1 ? 's' : ''} ago`
    }
    return 'just now'
}









export default function ComicCard({ data }: ComicCardProps) {
    const lc = data.latestChapter



    return (
        <Link
            href={`/series/${data.slug}`}
            className="group flex flex-col border border-[#2A2A2A] bg-[#111] transition-colors hover:border-white/20"
        >
            <div className="relative aspect-[720/1013] overflow-hidden bg-[#1A1A1A]">
                {data.cover ? (
                    <img
                        src={data.cover}
                        alt={data.title}
                        loading="lazy"
                        className="h-full w-full object-cover transition-opacity duration-300 group-hover:opacity-80"
                    />
                ) : (
                    <div className="flex h-full items-center justify-center text-xs text-[#555]">No Cover</div>
                )}

                <div className="absolute left-1.5 top-1.5 flex flex-col gap-1">
                    {data.isHot && <Badge variant="hot">HOT</Badge>}

                    {data.chapters.length > 0 && (
                            <Badge variant="new">{badgeLabel(data.chapters.length )}</Badge>

                    )}


                </div>

                <div className="absolute right-1.5 top-1.5">
                    <TypeBadge format={data.type} />
                </div>
            </div>

            <div className="flex flex-1 flex-col p-3">
                <h3
                    className="mb-1 line-clamp-2 text-sm font-bold leading-tight text-white transition-colors group-hover:text-[#999]"
                    title={data.title}
                >
                    {data.title}
                </h3>

                <div className="mb-2 flex items-center gap-2">
                    <Rating value={data.rating} />
                    {data.status && (
                        <Badge variant={data.status === 'ongoing' ? 'ongoing' : 'completed'}>
                            {data.status}
                        </Badge>
                    )}
                </div>

                {lc && (
                    <div className="mt-auto flex flex-col gap-px">
                        <div className="border-t border-[#2A2A2A] px-1 py-1.5">
                            <span className="block truncate text-[11px] font-medium text-[#777]">
                                {lc.title}
                                {lc.releasedAt && isWithin24h(lc.releasedAt) && (
                                    <span className="ml-1 text-[10px] font-bold text-white">NEW</span>
                                )}
                            </span>
                            {lc.releasedAt && (
                                <span className="block text-[10px] text-[#555]">
                                    {formatTimeAgo(lc.releasedAt)}
                                </span>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </Link>
    )
}
