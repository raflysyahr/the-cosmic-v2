import { type FC } from 'react'

const SIZES = {
  sm: 'h-6 w-6 text-xs',
  md: 'h-10 w-10 text-sm',
  lg: 'h-28 w-28 text-4xl',
} as const

interface AvatarProps {
  src?: string | null
  alt: string
  size?: keyof typeof SIZES | number
  border?: boolean
  className?: string
  asLabel?: boolean
  children?: React.ReactNode
  onClick?: () => void
}

const Avatar: FC<AvatarProps> = ({
  src,
  alt,
  size = 'md',
  border = false,
  className = '',
  asLabel = false,
  children,
  onClick,
}) => {
  const sizeClass = typeof size === 'string' ? SIZES[size] : ''
  const pxSize = typeof size === 'number' ? size : null
  const Tag = asLabel ? 'label' : onClick ? 'button' : 'div'

  const avatarContent = (
    <>
      {src ? (
        <img src={src} alt="" className="h-full w-full object-cover" />
      ) : (
        <span className="font-bold text-white">{alt.charAt(0).toUpperCase()}</span>
      )}
      {children}
    </>
  )

  const avatarCircle = (
    <Tag
      {...(asLabel ? {} : onClick ? { onClick } : {})}
      className={`relative flex items-center justify-center overflow-hidden rounded-[50%] bg-[#2A2A2A] ring-[3px] ring-gray-700 ring-offset-black ring-offset-[2px] transition-opacity hover:opacity-80 ${sizeClass} ${className}`}
      style={pxSize ? { width: pxSize, height: pxSize, fontSize: pxSize * 0.35 } : undefined}
    >
      {avatarContent}
    </Tag>
  )

  if (border) return avatarCircle

  return (
    <div
      className="relative inline-flex shrink-0 items-center justify-center"
      style={pxSize ? { width: pxSize + 8, height: pxSize + 8 } : undefined}
    >
      <div className="z-10 scale-[0.70]">{avatarCircle}</div>
        <div
        src="/images/avatar-border.png"
        alt=""
        className="pointer-events-none absolute inset-0 z-20 h-full w-full object-contain"
      />
    </div>
  )
}

export default Avatar
