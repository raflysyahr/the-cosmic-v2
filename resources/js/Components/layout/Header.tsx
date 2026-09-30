import { Link, router } from '@inertiajs/react'
import { Search, Menu, User, Bookmark, LogOut, MessageSquare } from 'lucide-react'
import { useState } from 'react'
import { useAuth } from '../../contexts/AuthContext'
import NotificationDropdown from '../discuss/NotificationDropdown'
import Avatar from '../ui/Avatar'

export default function Header() {
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
        <header className="sticky top-0 z-50 border-b border-[#2A2A2A] bg-black/95 backdrop-blur">
            <div className="mx-auto flex h-12 max-w-7xl items-center gap-4 px-4">
                <Link href="/" className="shrink-0 group">
                    <span className="text-xl tracking-tight text-white font-[BitcountGridDouble]">The Cosmic</span>
                </Link>

                <form onSubmit={handleSearch} className="hidden sm:flex flex-1 max-w-sm">
                    <div className="relative w-full">
                        <Search className="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#555]" />
                        <input
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search comics..."
                            className="h-8 w-full border border-[#2A2A2A] bg-[#111] px-8 text-xs text-white placeholder:text-[#555] transition-colors focus:border-white/30 focus:outline-none"
                        />
                    </div>
                </form>

                <nav className="hidden md:flex items-center gap-0 ml-auto">
                    <NavLink href="/series">All</NavLink>
                    <NavLink href="/series?type=manhua">Manhua</NavLink>
                    <NavLink href="/series?type=manhwa">Manhwa</NavLink>
                    <NavLink href="/series?type=manga">Manga</NavLink>
                    <NavLink href="/bookmarks">
                        <Bookmark className="h-3 w-3" />
                    </NavLink>
                    <NavLink href="/discuss">
                        <MessageSquare className="h-3 w-3" />
                    </NavLink>
                </nav>

                {loading ? (
                    <div className="h-5 w-16 animate-pulse rounded bg-[#2A2A2A]" />
                ) : user ? (
                    <div className="hidden md:flex items-center gap-2">
                        <NotificationDropdown />
                        <span className="text-xs text-[#555]">{user.displayName}</span>
                        <Link
                            href="/profile"
                            className="transition-colors hover:opacity-80"
                        >
                            <Avatar
                                src={user.avatarUrl}
                                alt={user.displayName}
                                size={20}
                                border
                            />
                        </Link>
                        <button
                            onClick={handleLogout}
                            className="flex items-center gap-1 px-2 py-1.5 text-xs text-[#555] transition-colors hover:text-white"
                            title="Logout"
                        >
                            <LogOut className="h-3 w-3" />
                        </button>
                    </div>
                ) : (
                    <div className="hidden md:flex">
                        <Link
                            href="/login"
                            className="flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-[#555] transition-colors hover:text-white"
                        >
                            <User className="h-3 w-3" /> Sign In
                        </Link>
                    </div>
                )}

                <div className="flex items-center ml-auto">
                <NavLink href="/discuss" className="md:hidden lg:hidden">
                    <MessageSquare className="h-4 w-4" />
                </NavLink>
                <button
                    onClick={() => setMenuOpen(!menuOpen)}
                    className="md:hidden ml-auto p-2 text-[#555] transition-colors hover:text-white"
                    aria-label="Menu"
                >
                    <Menu className="h-4 w-4" />
                </button>
                </div>
            </div>

            {menuOpen && (
                <div className="border-t border-[#2A2A2A] bg-black md:hidden">
                    <div className="flex flex-col gap-1 px-4 py-3">
                        <form onSubmit={handleSearch} className="mb-2">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#555]" />
                                <input
                                    type="text"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Search comics..."
                                    className="h-8 w-full border border-[#2A2A2A] bg-[#111] px-8 text-xs text-white placeholder:text-[#555] transition-colors focus:border-white/30 focus:outline-none"
                                />
                            </div>
                        </form>
                        <MobileNavLink href="/series" onClick={() => setMenuOpen(false)}>All</MobileNavLink>
                        <MobileNavLink href="/series?type=manhua" onClick={() => setMenuOpen(false)}>Manhua</MobileNavLink>
                        <MobileNavLink href="/series?type=manhwa" onClick={() => setMenuOpen(false)}>Manhwa</MobileNavLink>
                        <MobileNavLink href="/series?type=manga" onClick={() => setMenuOpen(false)}>Manga</MobileNavLink>
                        <MobileNavLink href="/bookmarks" onClick={() => setMenuOpen(false)}>Bookmarks</MobileNavLink>
                        {user && (
                            <div className="border-b border-[#2A2A2A] pb-2 mb-2">
                                <Link
                                    href="/profile"
                                    onClick={() => setMenuOpen(false)}
                                    className="flex items-center gap-3 px-3 py-2 transition-colors hover:bg-[#1A1A1A]"
                                >
                                    <Avatar
                                        src={user.avatarUrl}
                                        alt={user.displayName}
                                        size="md"
                                        border
                                    />
                                    <span className="truncate text-sm text-white">{user.displayName}</span>
                                </Link>
                                <button
                                    onClick={() => { handleLogout(); setMenuOpen(false) }}
                                    className="flex w-full items-center gap-2 px-3 py-2 text-xs text-[#555] transition-colors hover:bg-[#1A1A1A] hover:text-white"
                                >
                                    <LogOut className="h-3 w-3" />
                                    Sign Out
                                </button>
                            </div>
                        )}
                        {!user && (
                            <Link
                                href="/login"
                                onClick={() => setMenuOpen(false)}
                                className="flex w-full items-center gap-2 px-3 py-2 text-xs text-[#555] transition-colors hover:bg-[#1A1A1A] hover:text-white"
                            >
                                <User className="h-3 w-3" />
                                Sign In
                            </Link>
                        )}
                    </div>
                </div>
            )}
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
