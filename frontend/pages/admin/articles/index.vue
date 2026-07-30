<script setup lang="ts">
import type { Article } from '~/types/article'

definePageMeta({ role: 'admin' })

const api = useApi()
const articles = ref<Article[]>([])
const loading = ref(true)
const actingOn = ref<number | null>(null)
const statusFilter = ref('')

const statusLabel: Record<string, string> = {
  draft: 'Draft',
  published: 'Dipublikasikan',
}

const statusColor: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  published: 'bg-green-100 text-green-800',
}

async function loadArticles() {
  loading.value = true
  const query: Record<string, string> = {}
  if (statusFilter.value) query.status = statusFilter.value

  const response = await api<{ data: Article[] }>('/api/admin/articles', { query })
  articles.value = response.data
  loading.value = false
}

async function togglePublish(article: Article) {
  actingOn.value = article.id
  try {
    const endpoint = article.status === 'published' ? 'unpublish' : 'publish'
    await api(`/api/admin/articles/${article.id}/${endpoint}`, { method: 'POST' })
    await loadArticles()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mengubah status artikel.')
  } finally {
    actingOn.value = null
  }
}

async function deleteArticle(article: Article) {
  if (!confirm(`Hapus artikel "${article.title}"?`)) return

  actingOn.value = article.id
  try {
    await api(`/api/admin/articles/${article.id}`, { method: 'DELETE' })
    await loadArticles()
  } catch {
    alert('Gagal menghapus artikel.')
  } finally {
    actingOn.value = null
  }
}

onMounted(loadArticles)
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="font-display text-2xl font-bold text-gray-900">Kelola Artikel</h1>
      <NuxtLink to="/admin/articles/create" class="btn-primary">+ Tulis Artikel</NuxtLink>
    </div>

    <form class="mt-4 flex gap-3" @submit.prevent="loadArticles">
      <select v-model="statusFilter" class="field-input" @change="loadArticles">
        <option value="">Semua Status</option>
        <option value="draft">Draft</option>
        <option value="published">Dipublikasikan</option>
      </select>
    </form>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="articles.length === 0" class="mt-6 text-gray-600">Belum ada artikel.</p>

    <div v-else class="mt-6 space-y-3">
      <div v-for="article in articles" :key="article.id" class="card flex items-start justify-between p-4">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="font-display font-semibold text-gray-900">{{ article.title }}</h2>
            <span class="badge" :class="statusColor[article.status]">{{ statusLabel[article.status] }}</span>
          </div>
          <p class="text-sm text-gray-600">{{ article.category || 'Tanpa kategori' }} &middot; {{ article.author.name }}</p>
        </div>

        <div class="flex shrink-0 gap-2 text-sm">
          <NuxtLink :to="`/admin/articles/${article.id}/edit`" class="btn-outline !px-3 !py-1.5">Edit</NuxtLink>
          <button
            class="rounded-full px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
            :class="article.status === 'published' ? 'bg-gray-500 hover:bg-gray-600' : 'bg-green-700 hover:bg-green-800'"
            :disabled="actingOn === article.id"
            @click="togglePublish(article)"
          >
            {{ article.status === 'published' ? 'Batalkan Publikasi' : 'Publikasikan' }}
          </button>
          <button
            class="rounded-full bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
            :disabled="actingOn === article.id"
            @click="deleteArticle(article)"
          >
            Hapus
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
