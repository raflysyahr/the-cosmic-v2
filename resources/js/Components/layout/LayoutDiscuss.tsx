import { useEffect } from 'react'
import { usePage } from '@inertiajs/react'
import { TabHeader, DetailHeader } from './ShellHeader'
import { ShellChromeProvider, useShellChromeState } from './ShellChrome'
import { defaultTitle, isTabRoot, normalizePath } from './shellRoutes'

import Popup from '../ui/Popup'
import { usePopup } from '../../contexts/PopupContext'
import { useCultivationToast } from '../../contexts/CultivationToastContext'
import { cpToastNote, type ContributionAwardedPayload } from '../../lib/cpToast'
import { useAuth } from '../../contexts/AuthContext'
import NavigationBar from '../discuss/NavigationBar'
import CultivationToastStack from '../cultivation/CultivationToastStack'
import CpEventBanner from '../discuss/CpEventBanner'

function PopupLayer() {
    const { popup, closePopup } = usePopup()
    if (!popup) return null
    return <Popup config={popup} onClose={closePopup} />
}

function Shell({ children }: { children: React.ReactNode }) {
    const { auth } = usePage().props as { auth: { user: Record<string, unknown> | null } }
    const { setUser, user } = useAuth()
    const { showPopup } = usePopup()
    const { showCp } = useCultivationToast()
    const { url } = usePage()
    const chrome = useShellChromeState()

    // Tab utama = navbar bawah. Halaman lain = mode detail (tombol back saja).
    const path = normalizePath(url)
    const showNav = isTabRoot(path) && !chrome.hideNav

    useEffect(() => {
        if (auth?.user) setUser(auth.user as never)
    }, [auth, setUser])

    // Notifikasi personal "naik rank" — listener global (bukan cuma di
    // dalam Room) karena rank bisa naik lewat aktivitas di room manapun,
    // dan user boleh sedang berada di halaman lain saat itu terjadi.
    // Channel: app/Modules/Discuss/Events/MemberRankUpgraded.php.
    useEffect(() => {
        if (!user?.id || !window.Echo) return

        const channel = window.Echo.private(`user.${user.id}`)

        channel.listen('.App\\Modules\\Discuss\\Events\\MemberRankUpgraded', (e: { rank_name: string; rank_color?: string }) => {
            showPopup({
                type: 'notification',
                title: 'Rank Up!',
                message: `You've been promoted to ${e.rank_name}.`,
            })
        })

        // "+N CP" dari Discuss (kirim pesan, dapat reaksi/reply) — masuk ke
        // antrian toast yang sama dengan XP Cultivation. Nama event pakai
        // FQCN berawalan titik (sama seperti listener di Room.tsx): tanpa
        // titik, Echo menambah prefix "App.Events." dan tidak akan cocok
        // dengan event di modul Discuss.
        channel.listen('.App\\Modules\\Discuss\\Events\\ContributionAwarded', (e: ContributionAwardedPayload) => {
            showCp(e.amount, cpToastNote(e))
        })

        return () => {
            window.Echo?.leave(`user.${user.id}`)
        }
    }, [user?.id, showPopup, showCp])

    return (
        <>
            {/* Shell setinggi layar: header & navbar diam, hanya <main> yang scroll,
                jadi navbar tidak pernah tertimbun konten panjang. */}
            <div className="mx-auto flex h-[100dvh] w-full max-w-md flex-col overflow-hidden bg-surface">
                {showNav
                    ? <TabHeader />
                    : <DetailHeader path={path} title={chrome.title ?? defaultTitle(path)} onBack={chrome.onBack} />}
                <CpEventBanner />
                {/* scroll-region: Inertia mengembalikan scroll ke atas saat pindah halaman */}
                <main scroll-region="" className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                    {children}
                </main>
                {showNav && <NavigationBar />}
            </div>
            <PopupLayer />
            <CultivationToastStack className={showNav ? 'bottom-20 left-4' : 'bottom-4 left-4'} />
        </>
    )
}

export default function LayoutDiscuss({ children }: { children: React.ReactNode }) {
    return (
        <ShellChromeProvider>
            <Shell>{children}</Shell>
        </ShellChromeProvider>
    )
}
