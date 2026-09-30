interface BadgeProps {
    variant?: 'up' | 'ongoing' | 'completed' | 'hot' | 'new'
    children: React.ReactNode
}

export default function Badge({ variant, children }: BadgeProps) {
    const styles: Record<string, string> = {
        up: 'bg-white text-black text-[10px] font-bold',
        ongoing: 'border border-white/20 text-white text-[10px] font-bold',
        completed: 'border border-[#555] text-[#555] text-[10px] font-bold',
        hot: 'bg-red-500 text-white text-[10px] font-bold',
        new: 'bg-blue-500 text-white text-[10px] font-bold',
    }

    return (
        <span className={`inline-flex w-fit items-center px-2 py-[3px] uppercase tracking-wider ${styles[variant || 'ongoing']}`}>
            {children}
        </span>
    )
}
