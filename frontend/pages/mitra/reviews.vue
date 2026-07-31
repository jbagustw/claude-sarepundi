<script setup lang="ts">
import type { Review } from '~/types/review'

definePageMeta({ role: 'mitra' })

const api = useApi()
const reviews = ref<Review[]>([])
const loading = ref(true)
const replyDrafts = reactive<Record<number, string>>({})
const replying = ref<number | null>(null)
const errorMessages = reactive<Record<number, string>>({})

async function loadReviews() {
  loading.value = true
  const response = await api<{ data: Review[] }>('/api/mitra/reviews')
  reviews.value = response.data
  loading.value = false
}

async function reply(review: Review) {
  const message = replyDrafts[review.id]?.trim()
  if (!message) {
    errorMessages[review.id] = 'Isi balasan tidak boleh kosong.'
    return
  }

  errorMessages[review.id] = ''
  replying.value = review.id

  try {
    const response = await api<{ data: Review }>(`/api/mitra/reviews/${review.id}/reply`, {
      method: 'POST',
      body: { mitra_reply: message },
    })
    const index = reviews.value.findIndex(r => r.id === review.id)
    if (index !== -1) reviews.value[index] = response.data
  } catch (error: any) {
    errorMessages[review.id] = error?.data?.message || 'Gagal mengirim balasan.'
  } finally {
    replying.value = null
  }
}

onMounted(loadReviews)
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-gray-900">Ulasan</h1>
    <p class="mt-1 text-sm text-gray-600">Ulasan dari tamu untuk semua villa dan homestay kamu. Kamu bisa membalas setiap ulasan satu kali.</p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="reviews.length === 0" class="mt-6 text-gray-600">Belum ada ulasan.</p>

    <div v-else class="mt-6 space-y-4">
      <div v-for="review in reviews" :key="review.id" class="card p-4">
        <div class="flex items-center justify-between">
          <p class="font-semibold text-gray-900">{{ review.reviewable.name }}</p>
          <ReviewStars :rating="review.rating" />
        </div>
        <p class="text-xs text-gray-500">{{ review.user.name }} &middot; {{ new Date(review.created_at).toLocaleDateString('id-ID') }}</p>
        <p v-if="review.comment" class="mt-2 text-sm text-gray-700">{{ review.comment }}</p>

        <div v-if="review.mitra_reply" class="mt-3 rounded-xl bg-brand-sage/10 p-3 text-sm">
          <p class="font-medium text-gray-900">Balasanmu</p>
          <p class="mt-1 text-gray-700">{{ review.mitra_reply }}</p>
        </div>

        <div v-else class="mt-3">
          <textarea
            v-model="replyDrafts[review.id]"
            rows="2"
            maxlength="2000"
            placeholder="Tulis balasan untuk ulasan ini..."
            class="field-input rounded-2xl"
          />
          <button
            class="btn-primary mt-2 !px-3 !py-1.5"
            :disabled="replying === review.id"
            @click="reply(review)"
          >
            {{ replying === review.id ? 'Mengirim...' : 'Kirim Balasan' }}
          </button>
          <p v-if="errorMessages[review.id]" class="mt-1 text-sm text-red-600">{{ errorMessages[review.id] }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
