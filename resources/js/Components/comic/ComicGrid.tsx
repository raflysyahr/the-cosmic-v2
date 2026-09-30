import type { SeriesItem } from '../../types'
import ComicCard from './ComicCard'

interface ComicGridProps {
    items: SeriesItem[]
}

export default function ComicGrid({ items = [] }: ComicGridProps) {
    return (
        <div className="grid grid-cols-2 gap-px bg-[#2A2A2A] sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            {items.map((item) => (
                <ComicCard key={item.id} data={item} />
            ))}
        </div>
    )
}
