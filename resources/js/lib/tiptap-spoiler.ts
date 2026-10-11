import { Mark, markInputRule, mergeAttributes } from '@tiptap/core'

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    spoiler: {
      toggleSpoiler: () => ReturnType
    }
  }
}

/** Mark "spoiler" untuk editor pesan. Diketik sebagai ||teks|| atau Ctrl/Cmd+Shift+P. */
export const Spoiler = Mark.create({
  name: 'spoiler',

  inclusive: false,

  parseHTML() {
    return [{ tag: 'span[data-spoiler="true"]' }]
  },

  renderHTML({ HTMLAttributes }) {
    return ['span', mergeAttributes(HTMLAttributes, { 'data-spoiler': 'true', class: 'spoiler-text' }), 0]
  },

  addCommands() {
    return {
      toggleSpoiler:
        () =>
        ({ commands }) =>
          commands.toggleMark(this.name),
    }
  },

  addKeyboardShortcuts() {
    return {
      'Mod-Shift-p': () => this.editor.commands.toggleSpoiler(),
    }
  },

  addInputRules() {
    return [
      markInputRule({
        find: /(?:^|\s)(\|\|(?!\s+\|\|)((?:[^|]+))\|\|(?!\s+\|\|))$/,
        type: this.type,
      }),
    ]
  },
})
