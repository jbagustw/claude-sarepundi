<script setup lang="ts">
import type { ArticleFormPayload } from '~/types/article'

const props = defineProps<{
  modelValue: ArticleFormPayload
  submitting: boolean
  errors: Record<string, string[]>
  submitLabel: string
}>()

const emit = defineEmits<{
  'update:modelValue': [ArticleFormPayload]
  submit: []
}>()

const form = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})
</script>

<template>
  <form class="space-y-4" @submit.prevent="emit('submit')">
    <div>
      <label class="field-label" for="article-title">Judul</label>
      <input
        id="article-title"
        v-model="form.title"
        type="text"
        required
        class="field-input mt-1"
      >
      <p v-if="errors.title" class="mt-1 text-sm text-red-600">{{ errors.title[0] }}</p>
    </div>

    <div>
      <label class="field-label" for="article-category">Kategori</label>
      <input
        id="article-category"
        v-model="form.category"
        type="text"
        placeholder="mis. Tips Liburan, Tips Wisata"
        class="field-input mt-1"
      >
      <p v-if="errors.category" class="mt-1 text-sm text-red-600">{{ errors.category[0] }}</p>
    </div>

    <div>
      <label class="field-label" for="article-excerpt">Ringkasan Singkat</label>
      <textarea
        id="article-excerpt"
        v-model="form.excerpt"
        rows="2"
        maxlength="500"
        placeholder="Ringkasan yang tampil di kartu daftar artikel..."
        class="field-input mt-1 rounded-2xl"
      />
      <p v-if="errors.excerpt" class="mt-1 text-sm text-red-600">{{ errors.excerpt[0] }}</p>
    </div>

    <div>
      <label class="field-label" for="article-content">Isi Artikel</label>
      <RichTextEditor
        id="article-content"
        v-model="form.content"
        placeholder="Tulis isi artikel di sini..."
        class="mt-1"
      />
      <p v-if="errors.content" class="mt-1 text-sm text-red-600">{{ errors.content[0] }}</p>
    </div>

    <button type="submit" :disabled="submitting" class="btn-primary">
      {{ submitting ? 'Menyimpan...' : submitLabel }}
    </button>
  </form>
</template>
