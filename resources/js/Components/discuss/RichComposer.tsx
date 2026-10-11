import { useCallback, useEffect, useReducer, useRef, useState, type FC, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { EditorContent, useEditor, type Editor } from '@tiptap/react'
import { Extension, type JSONContent } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import { Bold, Italic, Underline as UnderlineIcon, Strikethrough, Code, EyeOff, Link2 } from 'lucide-react'
import { Spoiler } from '../../lib/tiptap-spoiler'
import {
  docFromText,
  normalizeLinkInput,
  serializeDoc,
  trimDraft,
  type DocNode,
  type TextEntity,
} from '../../lib/rich-text'

// Editor pesan (Tiptap) dengan format ala Telegram. Hasilnya selalu dikirim ke
// luar sebagai teks polos + daftar entity (lihat lib/rich-text.ts) — tidak ada
// HTML yang disimpan atau dirender.

export interface RichDraft {
  text: string
  entities: TextEntity[]
}

interface UseRichComposerOptions {
  /** Dipanggil setiap isi editor berubah (sudah di-trim). */
  onChange: (draft: RichDraft) => void
  /** Enter ditekan (di perangkat dengan keyboard fisik; di layar sentuh Enter = baris baru). */
  onSubmit: () => void
  /** Escape ditekan; kembalikan false/undefined bila tidak ditangani. */
  onEscape?: () => void
  initialText?: string
}

export interface RichComposer {
  editor: Editor | null
  focus: () => void
  insertText: (text: string) => void
  setContent: (text: string, entities?: unknown) => void
  clear: () => void
}

const isTouchDevice = () => typeof window !== 'undefined' && window.matchMedia?.('(pointer: coarse)').matches

export function useRichComposer({ onChange, onSubmit, onEscape, initialText = '' }: UseRichComposerOptions): RichComposer {
  // Extension dibuat sekali, jadi callback dibaca lewat ref agar selalu terbaru.
  const onChangeRef = useRef(onChange)
  const onSubmitRef = useRef(onSubmit)
  const onEscapeRef = useRef(onEscape)
  onChangeRef.current = onChange
  onSubmitRef.current = onSubmit
  onEscapeRef.current = onEscape

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        // Hanya teks inline: tanpa heading, list, kutipan, blok kode, dst.
        // (kalau dibiarkan, "- " atau ``` akan membuat blok yang tidak ikut terkirim).
        heading: false,
        blockquote: false,
        horizontalRule: false,
        bulletList: false,
        orderedList: false,
        listItem: false,
        codeBlock: false,
      }),
      Underline,
      Link.configure({ openOnClick: false, autolink: true, linkOnPaste: true }),
      Spoiler,
      Extension.create({
        name: 'composerKeys',
        priority: 1000,
        addKeyboardShortcuts() {
          return {
            Enter: () => {
              if (isTouchDevice()) return false
              onSubmitRef.current()
              return true
            },
            Escape: () => {
              if (!onEscapeRef.current) return false
              onEscapeRef.current()
              return true
            },
          }
        },
      }),
    ],
    content: docFromText(initialText, []) as JSONContent,
    editorProps: {
      attributes: {
        class: 'rich-editor scrollbar-none font-body-md text-body-md text-on-surface',
        'aria-label': 'Message input',
        autocapitalize: 'sentences',
        enterkeyhint: isTouchDevice() ? 'enter' : 'send',
      },
    },
    onUpdate: ({ editor: ed }) => {
      const raw = serializeDoc(ed.getJSON() as DocNode)
      onChangeRef.current(trimDraft(raw.text, raw.entities))
    },
  })

  const focus = useCallback(() => {
    editor?.commands.focus('end')
  }, [editor])

  const insertText = useCallback(
    (text: string) => {
      editor?.chain().focus().insertContent(text).run()
    },
    [editor],
  )

  const setContent = useCallback(
    (text: string, entities?: unknown) => {
      editor?.commands.setContent(docFromText(text, entities) as JSONContent, false)
    },
    [editor],
  )

  const clear = useCallback(() => {
    editor?.commands.clearContent(false)
  }, [editor])

  return { editor, focus, insertText, setContent, clear }
}

/** Re-render saat ada transaksi editor (supaya status tombol aktif & placeholder selalu benar). */
function useEditorRerender(editor: Editor | null) {
  const [, force] = useReducer((n: number) => n + 1, 0)
  useEffect(() => {
    if (!editor) return
    editor.on('transaction', force)
    return () => {
      editor.off('transaction', force)
    }
  }, [editor])
}

interface FormatButtonProps {
  title: string
  active: boolean
  onClick: () => void
  children: ReactNode
}

const FormatButton: FC<FormatButtonProps> = ({ title, active, onClick, children }) => (
  <button
    type="button"
    title={title}
    aria-label={title}
    aria-pressed={active}
    // Cegah editor kehilangan fokus/seleksi saat tombol ditekan.
    onMouseDown={(e) => e.preventDefault()}
    onClick={onClick}
    className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-colors ${
      active
        ? 'bg-primary-container text-on-primary-container'
        : 'text-on-surface-variant hover:bg-surface-container-higher hover:text-on-surface'
    }`}
  >
    {children}
  </button>
)

const FormatButtons: FC<{ editor: Editor }> = ({ editor }) => {
  const setLink = () => {
    const previous = editor.getAttributes('link').href as string | undefined
    const input = window.prompt('Link URL', previous ?? 'https://')
    if (input === null) return
    if (!input.trim()) {
      editor.chain().focus().extendMarkRange('link').unsetLink().run()
      return
    }
    const url = normalizeLinkInput(input)
    if (!url) {
      window.alert('Only http(s) and mailto links are supported.')
      return
    }
    editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
  }

  return (
    <>
      <FormatButton title="Bold" active={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()}>
        <Bold className="h-4 w-4" />
      </FormatButton>
      <FormatButton title="Italic" active={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()}>
        <Italic className="h-4 w-4" />
      </FormatButton>
      <FormatButton title="Underline" active={editor.isActive('underline')} onClick={() => editor.chain().focus().toggleUnderline().run()}>
        <UnderlineIcon className="h-4 w-4" />
      </FormatButton>
      <FormatButton title="Strikethrough" active={editor.isActive('strike')} onClick={() => editor.chain().focus().toggleStrike().run()}>
        <Strikethrough className="h-4 w-4" />
      </FormatButton>
      <span className="mx-0.5 h-[18px] w-px shrink-0 bg-outline-variant" />
      <FormatButton title="Monospace" active={editor.isActive('code')} onClick={() => editor.chain().focus().toggleCode().run()}>
        <Code className="h-4 w-4" />
      </FormatButton>
      <FormatButton title="Spoiler" active={editor.isActive('spoiler')} onClick={() => editor.chain().focus().toggleSpoiler().run()}>
        <EyeOff className="h-4 w-4" />
      </FormatButton>
      <FormatButton title="Link" active={editor.isActive('link')} onClick={setLink}>
        <Link2 className="h-4 w-4" />
      </FormatButton>
    </>
  )
}

/** Baris tombol format yang selalu tampil (untuk layar sentuh). */
export const FormatTray: FC<{ editor: Editor | null; className?: string }> = ({ editor, className = '' }) => {
  useEditorRerender(editor)
  if (!editor) return null
  return (
    <div className={`flex items-center gap-0.5 overflow-x-auto scrollbar-none bg-surface-container-highest px-3 pt-2 ${className}`}>
      <FormatButtons editor={editor} />
    </div>
  )
}

interface RichEditorFieldProps {
  editor: Editor | null
  placeholder: string
}

/** Area ketik + placeholder + toolbar kontekstual yang muncul saat teks diseleksi. */
export const RichEditorField: FC<RichEditorFieldProps> = ({ editor, placeholder }) => {
  useEditorRerender(editor)
  const [pos, setPos] = useState<{ left: number; top: number } | null>(null)
  const toolbarRef = useRef<HTMLDivElement>(null)

  const place = useCallback((ed: Editor) => {
    const { from, to, empty } = ed.state.selection
    if (empty || from === to) {
      setPos(null)
      return
    }
    const start = ed.view.coordsAtPos(from)
    const end = ed.view.coordsAtPos(to)
    const rect = toolbarRef.current?.getBoundingClientRect()
    const width = rect?.width ?? 260
    const height = rect?.height ?? 44
    const pad = 8
    const middle = (start.left + end.right) / 2
    const left = Math.max(pad, Math.min(middle - width / 2, window.innerWidth - width - pad))
    const top = start.top - height - pad >= pad ? start.top - height - pad : end.bottom + pad
    setPos({ left, top })
  }, [])

  useEffect(() => {
    if (!editor) return
    const onSelection = () => requestAnimationFrame(() => place(editor))
    const onBlur = () => setPos(null)
    editor.on('selectionUpdate', onSelection)
    editor.on('blur', onBlur)
    return () => {
      editor.off('selectionUpdate', onSelection)
      editor.off('blur', onBlur)
    }
  }, [editor, place])

  if (!editor) return null

  return (
    <div className="relative min-w-0 flex-1">
      {editor.isEmpty && (
        <span className="pointer-events-none absolute inset-x-0 top-0 truncate font-body-md text-body-md text-on-surface-variant">
          {placeholder}
        </span>
      )}
      <EditorContent editor={editor} />
      {pos &&
        createPortal(
          <div
            ref={toolbarRef}
            className="fixed z-[99999] flex w-max max-w-[calc(100vw-16px)] items-center gap-0.5 overflow-x-auto scrollbar-none rounded-xl bg-surface-container-highest p-1 shadow-lg ring-1 ring-outline-variant"
            style={{ left: pos.left, top: pos.top }}
            onMouseDown={(e) => e.preventDefault()}
          >
            <FormatButtons editor={editor} />
          </div>,
          document.body,
        )}
    </div>
  )
}
