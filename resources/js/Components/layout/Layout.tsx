import { useEffect } from 'react'
import { usePage } from '@inertiajs/react'
import Header from './Header'
import Footer from './Footer'
import Popup from '../ui/Popup'
import ModalHost from '../ui/Modal'
import CultivationToastStack from '../cultivation/CultivationToastStack'
import { usePopup } from '../../contexts/PopupContext'
import { useCultivationToast } from '../../contexts/CultivationToastContext'
import { cpToastNote, type ContributionAwardedPayload } from '../../lib/cpToast'
import { useAuth } from '../../contexts/AuthContext'

function PopupLayer() {
    const { popup, closePopup } = usePopup()
    if (!popup) return null
    return <Popup config={popup} onClose={closePopup} />
}

export default function Layout({ children }: { children: React.ReactNode }) {
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
        channel.listen('.App\\Modules\\Discuss\\Events\\ContributionAwarded', (e: ContributionAwardedPayload) => {
            showCp(e.amount, cpToastNote(e))
        })

        return () => {
            window.Echo?.leave(`user.${user.id}`)
        }
    }, [user?.id, showPopup, showCp])

    return (
        <>
            <div className="flex min-h-screen flex-col">
                <Header />
                <main className="flex-1">{children}</main>
                <Footer />
            </div>
            <PopupLayer />
            <CultivationToastStack />
            <ModalHost />
        </>
    )
}
