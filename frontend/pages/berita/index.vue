<script setup lang="ts">
import type { Article } from '~/types/article'

const api = useApi()
const route = useRoute()

const articles = ref<Article[]>([])
const loading = ref(true)

const activeCategory = computed(() => typeof route.query.category === 'string' ? route.query.category : '')

async function loadArticles() {
  loading.value = true
  const query: Record<string, string> = {}
  if (activeCategory.value) query.category = activeCategory.value

  const response = await api<{ data: Article[] }>('/api/articles', { query })
  articles.value = response.data
  loading.value = false
}

watch(activeCategory, loadArticles)
onMounted(loadArticles)
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-gray-900">Berita &amp; Artikel</h1>
    <p class="mt-1 text-sm text-gray-600">Tips wisata, tips liburan, dan info seputar dunia villa &amp; penginapan.</p>

    <div v-if="activeCategory" class="mt-4">
      <NuxtLink to="/berita" class="chip">
        &times; {{ activeCategory }}
      </NuxtLink>
    </div>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat artikel...</p>
    <p v-else-if="articles.length === 0" class="mt-6 text-gray-600">Belum ada artikel.</p>

    <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <NuxtLink
        v-for="article in articles"
        :key="article.id"
        :to="`/berita/${article.slug}`"
        class="card block overflow-hidden transition hover:shadow-md"
      >
        <img
          v-if="article.cover_image"
          :src="article.cover_image"
          class="h-44 w-full object-cover"
          alt=""
        >
        <div v-else class="flex h-44 w-full items-center justify-center bg-gray-100 text-sm text-gray-400">
          Belum ada gambar
        </div>
        <div class="p-4">
          <span v-if="article.category" class="badge bg-brand-sage/20 text-brand-brown-dark">{{ article.category }}</span>
          <h2 class="mt-2 font-display font-semibold text-gray-900">{{ article.title }}</h2>
          <p v-if="article.excerpt" class="mt-1 line-clamp-2 text-sm text-gray-600">{{ article.excerpt }}</p>
          <p class="mt-2 text-xs text-gray-400">
            {{ article.author.name }} &middot; {{ new Date(article.published_at ?? article.created_at).toLocaleDateString('id-ID') }}
          </p>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
