import { ChevronLeft, ChevronRight, ArrowRight } from 'lucide-react'
import { useState } from 'react'

function cn(...classes: (string | boolean | undefined | null)[]): string {
    return classes.filter(Boolean).join(' ')
}

interface PaginationProps {
    page: number
    lastPage: number
    onPageChange: (page: number) => void
}

export default function Pagination({ page, lastPage, onPageChange }: PaginationProps) {
    const [jumpValue, setJumpValue] = useState('')

    const pages: number[] = []
    const half = 1
    const start = Math.max(1, Math.min(page - half, lastPage - 2))
    const end = Math.min(lastPage, start + 2)
    for (let i = start; i <= end; i++) pages.push(i)

    const handleJump = () => {
        const n = parseInt(jumpValue)
        if (n >= 1 && n <= lastPage) {
            onPageChange(n)
            setJumpValue('')
        }
    }

    return (
        <div className="mt-8 flex items-center justify-center gap-1">
            <button
                onClick={() => onPageChange(page - 1)}
                disabled={page <= 1}
                className="inline-flex h-8 w-8 items-center justify-center border border-[#2A2A2A] bg-[#111] text-[#555] transition-colors hover:border-white/30 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                aria-label="Prev"
            >
                <ChevronLeft className="h-3.5 w-3.5" />
            </button>

            {pages.map((p) => (
                <button
                    key={p}
                    onClick={() => onPageChange(p)}
                    className={cn(
                        'flex h-8 min-w-8 items-center justify-center px-2 text-xs font-bold transition-colors',
                        p === page
                            ? 'bg-white text-black'
                            : 'border border-[#2A2A2A] bg-[#111] text-[#555] hover:border-white/30 hover:text-white'
                    )}
                >
                    {p}
                </button>
            ))}

            <button
                onClick={() => onPageChange(page + 1)}
                disabled={page >= lastPage}
                className="inline-flex h-8 w-8 items-center justify-center border border-[#2A2A2A] bg-[#111] text-[#555] transition-colors hover:border-white/30 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                aria-label="Next"
            >
                <ChevronRight className="h-3.5 w-3.5" />
            </button>

            <div className="mx-2 h-4 w-px bg-[#2A2A2A]" />

            <div className="flex items-center gap-1">
                <input
                    placeholder="#"
                    inputMode="numeric"
                    value={jumpValue}
                    onChange={(e) => setJumpValue(e.target.value.replace(/\D/g, ''))}
                    onKeyDown={(e) => e.key === 'Enter' && handleJump()}
                    className="h-8 w-10 border border-[#2A2A2A] bg-[#111] px-1 text-center text-xs font-bold text-white outline-none transition-colors placeholder:text-[#555] focus:border-white/30"
                    aria-label="Go to page"
                />
                <button
                    onClick={handleJump}
                    disabled={!jumpValue}
                    className="inline-flex h-8 w-8 items-center justify-center text-[#555] transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                    aria-label="Go"
                >
                    <ArrowRight className="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    )
}
