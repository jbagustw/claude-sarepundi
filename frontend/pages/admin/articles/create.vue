<script setup lang="ts">
import type { ArticleFormPayload } from '~/types/article'

definePageMeta({ role: 'admin' })

const api = useApi()
const router = useRouter()

const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<ArticleFormPayload>({
  title: '',
  category: '',
  excerpt: '',
  content: '',
})

async function handleSubmit() {
  errors.value = {}
  submitting.value = true

  try {
    const response = await api<{ data: { id: number } }>('/api/admin/articles', {
      method: 'POST',
      body: form.value,
    })
    router.push(`/admin/articles/${response.data.id}/edit`)
  } catch (error: any) {
    if (error?.data?.errors) errors.value = error.data.errors
    else alert('Gagal membuat artikel.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <h1 class="font-display text-2xl font-bold text-gray-900">Tulis Artikel</h1>
    <p class="mt-1 text-sm text-gray-600">
      Artikel baru akan tersimpan sebagai draft. Anda bisa menambahkan gambar sampul dan mempublikasikannya setelah disimpan.
    </p>

    <div class="mt-6">
      <ArticleForm
        v-model="form"
        :submitting="submitting"
        :errors="errors"
        submit-label="Simpan Artikel"
        @submit="handleSubmit"
      />
    </div>
  </div>
</template>
