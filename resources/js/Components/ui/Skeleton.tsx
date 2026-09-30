function cn(...classes: (string | boolean | undefined | null)[]): string {
    return classes.filter(Boolean).join(' ')
}

export default function Skeleton({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                'animate-pulse bg-[#1A1A1A]',
                className
            )}
        />
    )
}
