import { useState, useEffect } from 'react'
import { AlertTriangle, Info, Bell, HelpCircle, Edit3, X } from 'lucide-react'
import type { PopupConfig, PopupType } from '../../contexts/PopupContext'

interface PopupProps {
    config: PopupConfig
    onClose: () => void
}

export default function Popup({ config, onClose }: PopupProps) {
    const [inputValue, setInputValue] = useState(config.inputDefaultValue || '')
    const [visible, setVisible] = useState(false)

    useEffect(() => {
        const timer = requestAnimationFrame(() => setVisible(true))
        return () => cancelAnimationFrame(timer)
    }, [])

    const handleClose = () => {
        setVisible(false)
        setTimeout(onClose, 200)
    }

    const handleConfirm = () => {
        if (config.type === 'input') {
            config.onConfirm?.(inputValue)
        } else {
            config.onConfirm?.()
        }
        handleClose()
    }

    const handleCancel = () => {
        config.onCancel?.()
        handleClose()
    }

    const Icon = iconMap[config.type]
    const iconColor = colorMap[config.type]

    return (
        <div
            className={`fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm transition-opacity duration-200 ${visible ? 'opacity-100' : 'opacity-0'}`}
            onClick={(e) => {
                if (e.target === e.currentTarget && config.type !== 'confirm' && config.type !== 'input') {
                    handleClose()
                }
            }}
        >
            <div
                className={`mx-4 w-full max-w-sm border border-[#2A2A2A] bg-[#111] shadow-2xl transition-all duration-200 ${visible ? 'scale-100 opacity-100' : 'scale-95 opacity-0'}`}
            >
                <div className="flex items-center justify-between border-b border-[#2A2A2A] px-5 py-3">
                    <div className="flex items-center gap-2">
                        {Icon && <Icon className={`h-4 w-4 ${iconColor}`} />}
                        <span className="text-sm font-bold text-white">{config.title}</span>
                    </div>
                    <button
                        onClick={handleClose}
                        className="text-[#555] transition-colors hover:text-white"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>

                <div className="px-5 py-4">
                    {config.message && (
                        <p className="text-sm leading-relaxed text-[#777]">{config.message}</p>
                    )}
                    {config.children}

                    {config.type === 'input' && (
                        <input
                            type="text"
                            value={inputValue}
                            onChange={(e) => setInputValue(e.target.value)}
                            placeholder={config.inputPlaceholder || 'Enter value...'}
                            className="mt-3 h-9 w-full border border-[#2A2A2A] bg-[#0a0a0a] px-3 text-xs text-white placeholder:text-[#555] focus:border-white/30 focus:outline-none"
                            autoFocus
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') handleConfirm()
                            }}
                        />
                    )}
                </div>

                <div className="flex items-center justify-end gap-2 border-t border-[#2A2A2A] px-5 py-3">
                    {(config.type === 'confirm' || config.type === 'input') && (
                        <button
                            onClick={handleCancel}
                            className="border border-[#2A2A2A] px-4 py-1.5 text-xs font-semibold text-[#777] transition-colors hover:border-white/30 hover:text-white"
                        >
                            {config.cancelText || 'Cancel'}
                        </button>
                    )}
                    <button
                        onClick={handleConfirm}
                        className={`px-4 py-1.5 text-xs font-bold transition-colors ${
                            config.type === 'warning'
                                ? 'bg-red-600 text-white hover:bg-red-700'
                                : 'bg-white text-black hover:bg-[#ccc]'
                        }`}
                    >
                        {config.confirmText || (config.type === 'confirm' || config.type === 'input' ? 'Confirm' : 'OK')}
                    </button>
                </div>
            </div>
        </div>
    )
}

const iconMap: Record<PopupType, React.ComponentType<{ className?: string }>> = {
    notification: Bell,
    warning: AlertTriangle,
    info: Info,
    confirm: HelpCircle,
    input: Edit3,
}

const colorMap: Record<PopupType, string> = {
    notification: 'text-blue-400',
    warning: 'text-red-400',
    info: 'text-white',
    confirm: 'text-yellow-400',
    input: 'text-green-400',
}
