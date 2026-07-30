<script setup lang="ts">
import type { Article, ArticleFormPayload } from '~/types/article'

definePageMeta({ role: 'admin' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const articleId = route.params.id as string

const article = ref<Article | null>(null)
const loading = ref(true)
const submitting = ref(false)
const uploading = ref(false)
const togglingPublish = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<ArticleFormPayload>({
  title: '',
  category: '',
  excerpt: '',
  content: '',
})

async function loadArticle() {
  loading.value = true
  const response = await api<{ data: Article }>(`/api/admin/articles/${articleId}`)
  article.value = response.data
  form.value = {
    title: response.data.title,
    category: response.data.category ?? '',
    excerpt: response.data.excerpt ?? '',
    content: response.data.content,
  }
  loading.value = false
}

async function handleSubmit() {
  errors.value = {}
  submitting.value = true

  try {
    const response = await api<{ data: Article }>(`/api/admin/articles/${articleId}`, {
      method: 'PATCH',
      body: form.value,
    })
    article.value = response.data
  } catch (error: any) {
    if (error?.data?.errors) errors.value = error.data.errors
    else alert('Gagal menyimpan perubahan.')
  } finally {
    submitting.value = false
  }
}

async function handleCoverUpload(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  uploading.value = true
  try {
    const body = new FormData()
    body.append('cover', file)
    const response = await api<{ data: Article }>(`/api/admin/articles/${articleId}/cover`, { method: 'POST', body })
    article.value = response.data
  } catch {
    alert('Gagal mengunggah gambar sampul.')
  } finally {
    uploading.value = false
    input.value = ''
  }
}

async function togglePublish() {
  if (!article.value) return

  togglingPublish.value = true
  try {
    const endpoint = article.value.status === 'published' ? 'unpublish' : 'publish'
    const response = await api<{ data: Article }>(`/api/admin/articles/${articleId}/${endpoint}`, { method: 'POST' })
    article.value = response.data
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mengubah status artikel.')
  } finally {
    togglingPublish.value = false
  }
}

async function deleteArticle() {
  if (!article.value || !confirm(`Hapus artikel "${article.value.title}"?`)) return

  await api(`/api/admin/articles/${articleId}`, { method: 'DELETE' })
  router.push('/admin/articles')
}

onMounted(loadArticle)
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <p v-if="loading">Memuat...</p>

    <template v-else-if="article">
      <div class="flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-gray-900">Edit Artikel</h1>
        <span class="badge" :class="article.status === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'">
          {{ article.status === 'published' ? 'Dipublikasikan' : 'Draft' }}
        </span>
      </div>

      <div class="mt-4 flex gap-2">
        <button
          class="rounded-full px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
          :class="article.status === 'published' ? 'bg-gray-500 hover:bg-gray-600' : 'bg-green-700 hover:bg-green-800'"
          :disabled="togglingPublish"
          @click="togglePublish"
        >
          {{ article.status === 'published' ? 'Batalkan Publikasi' : 'Publikasikan' }}
        </button>
        <button class="btn-danger-outline" @click="deleteArticle">
          Hapus Artikel
        </button>
      </div>

      <div class="mt-6">
        <span class="field-label">Gambar Sampul</span>
        <div class="mt-2">
          <img
            v-if="article.cover_image"
            :src="article.cover_image"
            class="h-40 w-full rounded-xl object-cover sm:w-64"
            alt=""
          >
          <div v-else class="flex h-40 w-full items-center justify-center rounded-xl bg-gray-100 text-sm text-gray-400 sm:w-64">
            Belum ada gambar
          </div>
        </div>
        <label class="mt-3 inline-block cursor-pointer text-sm text-brand-brown underline">
          {{ uploading ? 'Mengunggah...' : (article.cover_image ? '+ Ganti Gambar' : '+ Unggah Gambar') }}
          <input type="file" accept="image/*" class="hidden" :disabled="uploading" @change="handleCoverUpload">
        </label>
      </div>

      <div class="mt-6">
        <ArticleForm
          v-model="form"
          :submitting="submitting"
          :errors="errors"
          submit-label="Simpan Perubahan"
          @submit="handleSubmit"
        />
      </div>
    </template>
  </div>
</template>
