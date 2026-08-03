<script setup lang="ts">
import type { Article } from '~/types/article'

const route = useRoute()
const api = useApi()

const article = ref<Article | null>(null)
const notFound = ref(false)

onMounted(async () => {
  try {
    const response = await api<{ data: Article }>(`/api/articles/${route.params.slug}`)
    article.value = response.data
  } catch {
    notFound.value = true
  }
})
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <p v-if="notFound" class="text-gray-600">Artikel tidak ditemukan.</p>

    <article v-else-if="article">
      <NuxtLink
        v-if="article.category"
        :to="{ path: '/berita', query: { category: article.category } }"
        class="chip"
      >
        {{ article.category }}
      </NuxtLink>

      <h1 class="mt-3 font-display text-2xl font-bold text-gray-900 sm:text-3xl">{{ article.title }}</h1>
      <p class="mt-2 text-sm text-gray-500">
        {{ article.author.name }} &middot; {{ new Date(article.published_at ?? article.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
      </p>

      <img
        v-if="article.cover_image"
        :src="article.cover_image"
        class="mt-6 h-64 w-full rounded-2xl object-cover sm:h-96"
        alt=""
      >

      <div class="card prose prose-sm sm:prose-base mt-6 max-w-none p-6 text-gray-700" v-html="article.content" />

      <div class="mt-6">
        <NuxtLink to="/berita" class="btn-outline">&larr; Kembali ke Berita &amp; Artikel</NuxtLink>
      </div>
    </article>
  </div>
</template>
