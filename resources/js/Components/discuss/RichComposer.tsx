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

export interface LinkDialogState {
  /** URL yang sudah ada di seleksi/kursor (kosong bila belum ada link). */
  url: string
  hasLink: boolean
}

export interface RichComposer {
  editor: Editor | null
  focus: () => void
  insertText: (text: string) => void
  setContent: (text: string, entities?: unknown) => void
  clear: () => void
  /** Popup link (menggantikan window.prompt). `null` = tertutup. */
  linkDialog: LinkDialogState | null
  openLinkDialog: () => void
  closeLinkDialog: () => void
  /** Mengembalikan false bila URL tidak valid/tidak didukung. */
  applyLink: (raw: string) => boolean
  removeLink: () => void
}

const isTouchDevice = () => typeof window !== 'undefined' && window.matchMedia?.('(pointer: coarse)').matches

export function useRichComposer({ onChange, onSubmit, onEscape, initialText = '' }: UseRichComposerOptions): RichComposer {
  const [linkDialog, setLinkDialog] = useState<LinkDialogState | null>(null)
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

  const openLinkDialog = useCallback(() => {
    if (!editor) return
    const href = editor.getAttributes('link').href as string | undefined
    setLinkDialog({ url: href ?? '', hasLink: editor.isActive('link') })
  }, [editor])

  const closeLinkDialog = useCallback(() => {
    setLinkDialog(null)
    editor?.commands.focus()
  }, [editor])

  const applyLink = useCallback(
    (raw: string): boolean => {
      if (!editor) return false
      const url = normalizeLinkInput(raw)
      if (!url) return false
      if (editor.state.selection.empty && !editor.isActive('link')) {
        // Tanpa seleksi: sisipkan URL-nya sebagai teks bertaut.
        editor
          .chain()
          .focus()
          .insertContent({ type: 'text', text: url, marks: [{ type: 'link', attrs: { href: url } }] })
          .run()
      } else {
        editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
      }
      setLinkDialog(null)
      return true
    },
    [editor],
  )

  const removeLink = useCallback(() => {
    editor?.chain().focus().extendMarkRange('link').unsetLink().run()
    setLinkDialog(null)
  }, [editor])

  return { editor, focus, insertText, setContent, clear, linkDialog, openLinkDialog, closeLinkDialog, applyLink, removeLink }
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

const FormatButtons: FC<{ rich: RichComposer }> = ({ rich }) => {
  const editor = rich.editor
  if (!editor) return null

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
      <FormatButton title="Link" active={editor.isActive('link')} onClick={rich.openLinkDialog}>
        <Link2 className="h-4 w-4" />
      </FormatButton>
    </>
  )
}

/** Baris tombol format yang selalu tampil (untuk layar sentuh). */
export const FormatTray: FC<{ rich: RichComposer; className?: string }> = ({ rich, className = '' }) => {
  useEditorRerender(rich.editor)
  if (!rich.editor) return null
  return (
    <div className={`flex items-center gap-0.5 overflow-x-auto scrollbar-none bg-surface-container-highest px-3 pt-2 ${className}`}>
      <FormatButtons rich={rich} />
    </div>
  )
}

/** Popup untuk menambah/mengubah/menghapus link (menggantikan window.prompt). */
const LinkDialog: FC<{ rich: RichComposer }> = ({ rich }) => {
  const dialog = rich.linkDialog
  const [value, setValue] = useState('')
  const [invalid, setInvalid] = useState(false)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (!dialog) return
    setValue(dialog.url)
    setInvalid(false)
    requestAnimationFrame(() => {
      inputRef.current?.focus()
      inputRef.current?.select()
    })
  }, [dialog])

  if (!dialog) return null

  const submit = () => {
    if (!rich.applyLink(value)) setInvalid(true)
  }
  const title = dialog.hasLink ? 'Edit link' : 'Add link'

  return createPortal(
    <div
      className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 p-4"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) rich.closeLinkDialog()
      }}
    >
      <div role="dialog" aria-modal="true" aria-label={title} className="w-full max-w-sm rounded-2xl bg-surface-container p-4 shadow-xl">
        <p className="font-label-md text-label-md text-on-surface">{title}</p>
        <input
          ref={inputRef}
          type="text"
          inputMode="url"
          autoCapitalize="off"
          autoCorrect="off"
          spellCheck={false}
          value={value}
          onChange={(e) => {
            setValue(e.target.value)
            setInvalid(false)
          }}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault()
              submit()
            } else if (e.key === 'Escape') {
              e.preventDefault()
              e.stopPropagation()
              rich.closeLinkDialog()
            }
          }}
          placeholder="https://example.com"
          aria-invalid={invalid}
          className="mt-3 w-full rounded-xl border-none bg-surface-container-highest px-3 py-2.5 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant focus:ring-0"
        />
        {invalid && (
          <p className="mt-1.5 font-body-sm text-body-sm text-error">Enter a valid http(s) or mailto link.</p>
        )}
        <div className="mt-4 flex items-center justify-end gap-2">
          {dialog.hasLink && (
            <button
              type="button"
              onClick={rich.removeLink}
              className="mr-auto rounded-full px-3 py-1.5 font-label-md text-label-md text-error transition-colors hover:bg-surface-container-highest"
            >
              Remove link
            </button>
          )}
          <button
            type="button"
            onClick={rich.closeLinkDialog}
            className="rounded-full px-3 py-1.5 font-label-md text-label-md text-on-surface-variant transition-colors hover:bg-surface-container-highest"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={submit}
            className="rounded-full bg-primary px-4 py-1.5 font-label-md text-label-md text-on-primary transition-transform active:scale-95"
          >
            Save
          </button>
        </div>
      </div>
    </div>,
    document.body,
  )
}

interface RichEditorFieldProps {
  rich: RichComposer
  placeholder: string
}

/** Area ketik + placeholder + toolbar kontekstual yang muncul saat teks diseleksi. */
export const RichEditorField: FC<RichEditorFieldProps> = ({ rich, placeholder }) => {
  const editor = rich.editor
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
            <FormatButtons rich={rich} />
          </div>,
          document.body,
        )}
      <LinkDialog rich={rich} />
    </div>
  )
}
