import type { ReactNode } from 'react'
import { Link, router } from '@inertiajs/react'
import { ArrowLeft } from 'lucide-react'
import { parentPath } from './shellRoutes'

/** Header mode tab utama: hanya logo. */
export function TabHeader() {
    return (
        <header className="shrink-0 bg-surface pt-[env(safe-area-inset-top)] backdrop-blur">
            <div className="flex h-12 pt-10 pb-5 items-center px-4">
                <Link href="/" className="shrink-0">
                    <span className="text-xl tracking-tight text-white font-[BitcountGridDouble]">The Cosmic</span>
                </Link>
            </div>
        </header>
    )
}

interface DetailHeaderProps {
    path: string
    title: string
    onBack?: () => void
    right?: ReactNode
}

/** Header mode detail: tombol back + judul. Navigasi bawah tidak tampil. */
export function DetailHeader({ path, title, onBack, right }: DetailHeaderProps) {
    const goBack = () => {
        if (onBack) return onBack()

        const parent = parentPath(path)
        if (parent === null && window.history.length > 1) {
            window.history.back()
            return
        }
        router.visit(parent ?? '/discuss')
    }

    return (
        <header className="shrink-0 border-b border-neutral-900 bg-black/95 pt-[env(safe-area-inset-top)]  backdrop-blur">
            <div className="flex h-12 pt-10 pb-5 items-center gap-1 px-1">
                <button
                    type="button"
                    onClick={goBack}
                    aria-label="Back"
                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-neutral-300 transition-colors hover:bg-neutral-900 hover:text-white"
                >
                    <ArrowLeft className="h-5 w-5" />
                </button>
                <h1 className="min-w-0 flex-1 truncate pr-3 text-[15px] font-semibold text-white">{title}</h1>
                {right && <div className="relative shrink-0 pr-1">{right}</div>}
            </div>
        </header>
    )
}
