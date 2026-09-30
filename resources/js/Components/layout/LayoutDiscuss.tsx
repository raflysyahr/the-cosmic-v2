import { useEffect,useState } from 'react'
import { usePage } from '@inertiajs/react'
import HeaderDiscuss from './HeaderDiscuss'

import Popup from '../ui/Popup'
import { usePopup } from '../../contexts/PopupContext'
import { useCultivationToast } from '../../contexts/CultivationToastContext'
import { useAuth } from '../../contexts/AuthContext'
import NavigationBar from '../discuss/NavigationBar'
import CultivationToastStack from '../cultivation/CultivationToastStack'

function PopupLayer() {
    const { popup, closePopup } = usePopup()
    if (!popup) return null
    return <Popup config={popup} onClose={closePopup} />
}

export default function LayoutDiscuss({ children }: { children: React.ReactNode }) {
    const [activeTab,setActiveTab] = useState("chats")
    const { auth } = usePage().props as { auth: { user: Record<string, unknown> | null } }
    const { setUser, user } = useAuth()
    const { showPopup } = usePopup()
    const { showCp } = useCultivationToast()

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
        channel.listen('.App\\Modules\\Discuss\\Events\\ContributionAwarded', (e: { amount: number; multiplier?: number; event_name?: string | null }) => {
            const note = e.multiplier && e.multiplier > 1
                ? `x${e.multiplier}${e.event_name ? ` ${e.event_name}` : ''}`
                : undefined
            showCp(e.amount, note)
        })

        return () => {
            window.Echo?.leave(`user.${user.id}`)
        }
    }, [user?.id, showPopup, showCp])

    return (
        <>
            <div className="flex min-h-screen flex-col">
                <HeaderDiscuss />
                <main className="flex-1">{children}</main>
                <NavigationBar active={activeTab} setActive={setActiveTab} />
            </div>
            <PopupLayer />
            <CultivationToastStack className="bottom-20 left-4" />
        </>
    )
}
