import { Star } from 'lucide-react'

export default function Rating({ value }: { value?: number }) {
    if (!value) return null
    return (
        <div className="inline-flex items-center gap-1">
            <Star className="h-3 w-3 fill-white text-white" />
            <span className="text-xs font-bold text-white">{value}</span>
        </div>
    )
}
