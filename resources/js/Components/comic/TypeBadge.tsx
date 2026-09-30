const formatStyles: Record<string, string> = {
    manhua: 'bg-white text-black',
    manhwa: 'bg-white text-black',
    manga: 'bg-white text-black',
    webtoon: 'bg-white text-black',
}

export default function TypeBadge({ format }: { format?: string }) {
    if (!format) return null
    const style = formatStyles[format.toLowerCase()] || 'bg-white text-black'
    return (
        <span className={`inline-flex items-center px-1.5 py-[2px] text-[10px] font-bold uppercase tracking-wider ${style}`}>
            {format}
        </span>
    )
}
