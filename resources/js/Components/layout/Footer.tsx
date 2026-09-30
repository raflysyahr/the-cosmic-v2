import { Link } from '@inertiajs/react'

export default function Footer() {
    return (
        <footer className="mt-auto border-t border-[#2A2A2A]">
            <div className="mx-auto max-w-7xl px-4 py-8">
                <div className="mb-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
                    <span className="text-sm  font-bold tracking-tight text-white font-['BitcountGridDouble']">
                        The Cosmic
                    </span>
                    <div className="flex items-center gap-3 text-[10px] text-[#555]">
                        <Link href="/about" className="transition-colors hover:text-[#999]">About</Link>
                        <span className="text-[#2A2A2A]">·</span>
                        <Link href="/privacy" className="transition-colors hover:text-[#999]">Privacy</Link>
                        <span className="text-[#2A2A2A]">·</span>
                        <Link href="/dmca" className="transition-colors hover:text-[#999]">DMCA</Link>
                        <span className="text-[#2A2A2A]">·</span>
                        <Link href="/contact" className="transition-colors hover:text-[#999]">Contact</Link>
                    </div>
                </div>
                <div className="border-t border-[#2A2A2A] pt-4 text-center text-[10px] text-[#555]">
                    <p className="mb-1">&copy; 2026 The Cosmic. All rights reserved.</p>
                    <p>Built with reader-first experience</p>
                </div>
            </div>
        </footer>
    )
}
