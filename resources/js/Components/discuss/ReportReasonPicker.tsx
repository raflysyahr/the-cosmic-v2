import { useState, type FC } from 'react'

const REASONS: { value: string; label: string }[] = [
  { value: 'spam', label: 'Spam' },
  { value: 'harassment', label: 'Harassment' },
  { value: 'inappropriate', label: 'Inappropriate content' },
  { value: 'manipulation', label: 'Points manipulation' },
  { value: 'other', label: 'Other' },
]

interface ReportReasonPickerProps {
  onChange: (reason: string) => void
}

/** Pilihan alasan untuk popup "Report message" (state sendiri, hasil dilaporkan lewat onChange). */
const ReportReasonPicker: FC<ReportReasonPickerProps> = ({ onChange }) => {
  const [value, setValue] = useState('spam')

  return (
    <div className="mt-1 flex flex-col gap-1.5">
      {REASONS.map((reason) => (
        <label key={reason.value} className="flex cursor-pointer items-center gap-2 text-sm text-white">
          <input
            type="radio"
            name="report-reason"
            checked={value === reason.value}
            onChange={() => { setValue(reason.value); onChange(reason.value) }}
          />
          {reason.label}
        </label>
      ))}
    </div>
  )
}

export default ReportReasonPicker
