import { Link } from '@inertiajs/react'

export default function GenreTag({ name }: { name: string }) {
    const slug = name.toLowerCase().replace(/\s+/g, '-')
    return (
        <Link
            href={`/comics/${slug}`}
            className="inline-block border border-[#2A2A2A] px-2.5 py-1 text-xs font-medium text-[#777] transition-colors hover:border-white/30 hover:text-white"
        >
            {name}
        </Link>
    )
}
