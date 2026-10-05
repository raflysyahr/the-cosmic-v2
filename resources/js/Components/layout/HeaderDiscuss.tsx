import { Link, router } from '@inertiajs/react'
import { Search, Menu, User, Bookmark, LogOut, MessageSquare } from 'lucide-react'
import { useState } from 'react'
import { useAuth } from '../../contexts/AuthContext'
import NotificationDropdown from '../discuss/NotificationDropdown'
import Avatar from '../ui/Avatar'

export default function HeaderDiscuss() {
    const { user, loading, logout } = useAuth()
    const [query, setQuery] = useState('')
    const [menuOpen, setMenuOpen] = useState(false)

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault()
        if (query.trim()) {
            router.visit(`/search?q=${encodeURIComponent(query.trim())}`)
            setQuery('')
            setMenuOpen(false)
        }
    }

    const handleLogout = () => {
        logout().finally(() => router.visit('/', { replace: true }))
    }

    return (
        <header className="border-b-0 shrink-0 border-[#2A2A2A] bg-black/95 backdrop-blur">
            <div className="mx-auto flex h-12 max-w-7xl items-center gap-4 px-4 ">
                <Link href="/" className="shrink-0 group">
                    <span className="text-xl tracking-tight text-white font-[BitcountGridDouble]">The Cosmic</span>
                </Link>
            </div>
        </header>
    )
}

function NavLink({ href, children }: { href: string; children: React.ReactNode }) {
    return (
        <Link
            href={href}
            className="px-3 py-1.5 text-xs font-semibold text-[#555] transition-colors hover:bg-[#1A1A1A] hover:text-white"
        >
            {children}
        </Link>
    )
}

function MobileNavLink({ href, onClick, children }: { href: string; onClick: () => void; children: React.ReactNode }) {
    return (
        <Link
            href={href}
            onClick={onClick}
            className="px-3 py-2 text-xs font-semibold text-[#555] transition-colors hover:bg-[#1A1A1A] hover:text-white"
        >
            {children}
        </Link>
    )
}
