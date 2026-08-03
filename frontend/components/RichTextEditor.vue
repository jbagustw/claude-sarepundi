<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'

const props = defineProps<{
  modelValue: string
  placeholder?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [string]
}>()

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit,
    Link.configure({ openOnClick: false, autolink: true }),
    Placeholder.configure({ placeholder: props.placeholder ?? 'Tulis di sini...' }),
  ],
  editorProps: {
    attributes: { class: 'prose prose-sm max-w-none min-h-[10rem] px-3 py-2 focus:outline-none' },
  },
  onUpdate: ({ editor }) => emit('update:modelValue', editor.getHTML()),
})

// Keep the editor in sync if the parent resets modelValue (e.g. loading data after mount).
watch(() => props.modelValue, (value) => {
  if (editor.value && value !== editor.value.getHTML()) {
    editor.value.commands.setContent(value, false)
  }
})

function setLink() {
  const previousUrl = editor.value?.getAttributes('link').href as string | undefined
  const url = window.prompt('Masukkan URL link:', previousUrl ?? 'https://')
  if (url === null) return

  if (url === '') {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }

  editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

onBeforeUnmount(() => editor.value?.destroy())
</script>

<template>
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white focus-within:border-brand-brown focus-within:ring-1 focus-within:ring-brand-brown/30">
    <div v-if="editor" class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50 px-2 py-1.5">
      <button
        type="button"
        title="Tebal"
        class="toolbar-btn"
        :class="{ 'toolbar-btn-active': editor.isActive('bold') }"
        @click="editor.chain().focus().toggleBold().run()"
      >
        <strong>B</strong>
      </button>
      <button
        type="button"
        title="Miring"
        class="toolbar-btn italic"
        :class="{ 'toolbar-btn-active': editor.isActive('italic') }"
        @click="editor.chain().focus().toggleItalic().run()"
      >
        I
      </button>
      <button
        type="button"
        title="Coret"
        class="toolbar-btn line-through"
        :class="{ 'toolbar-btn-active': editor.isActive('strike') }"
        @click="editor.chain().focus().toggleStrike().run()"
      >
        S
      </button>

      <span class="mx-1 h-5 w-px bg-gray-200" />

      <button
        type="button"
        title="Judul Bagian"
        class="toolbar-btn"
        :class="{ 'toolbar-btn-active': editor.isActive('heading', { level: 2 }) }"
        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
      >
        H2
      </button>
      <button
        type="button"
        title="Sub Judul"
        class="toolbar-btn"
        :class="{ 'toolbar-btn-active': editor.isActive('heading', { level: 3 }) }"
        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
      >
        H3
      </button>

      <span class="mx-1 h-5 w-px bg-gray-200" />

      <button
        type="button"
        title="Daftar Berpoin"
        class="toolbar-btn"
        :class="{ 'toolbar-btn-active': editor.isActive('bulletList') }"
        @click="editor.chain().focus().toggleBulletList().run()"
      >
        •≡
      </button>
      <button
        type="button"
        title="Daftar Bernomor"
        class="toolbar-btn"
        :class="{ 'toolbar-btn-active': editor.isActive('orderedList') }"
        @click="editor.chain().focus().toggleOrderedList().run()"
      >
        1.≡
      </button>
      <button
        type="button"
        title="Kutipan"
        class="toolbar-btn"
        :class="{ 'toolbar-btn-active': editor.isActive('blockquote') }"
        @click="editor.chain().focus().toggleBlockquote().run()"
      >
        &ldquo;&rdquo;
      </button>
      <button
        type="button"
        title="Link"
        class="toolbar-btn"
        :class="{ 'toolbar-btn-active': editor.isActive('link') }"
        @click="setLink"
      >
        🔗
      </button>

      <span class="mx-1 h-5 w-px bg-gray-200" />

      <button
        type="button"
        title="Urungkan"
        class="toolbar-btn"
        :disabled="!editor.can().undo()"
        @click="editor.chain().focus().undo().run()"
      >
        ↺
      </button>
      <button
        type="button"
        title="Ulangi"
        class="toolbar-btn"
        :disabled="!editor.can().redo()"
        @click="editor.chain().focus().redo().run()"
      >
        ↻
      </button>
    </div>

    <EditorContent :editor="editor" />
  </div>
</template>

<style scoped>
.toolbar-btn {
  @apply flex h-7 min-w-7 items-center justify-center rounded px-1.5 text-xs font-medium text-gray-600 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-40;
}
.toolbar-btn-active {
  @apply bg-brand-brown text-white hover:bg-brand-brown;
}
</style>
