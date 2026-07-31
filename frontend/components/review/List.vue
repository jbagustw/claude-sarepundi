<script setup lang="ts">
import type { Review } from '~/types/review'

const props = defineProps<{
  resourceType: 'villa' | 'homestay'
  resourceSlug: string
}>()

const api = useApi()
const reviews = ref<Review[]>([])
const loading = ref(true)

const resourcePathSegment = computed(() => (props.resourceType === 'homestay' ? 'homestays' : 'villas'))

async function loadReviews() {
  loading.value = true
  const response = await api<{ data: Review[] }>(`/api/${resourcePathSegment.value}/${props.resourceSlug}/reviews`)
  reviews.value = response.data
  loading.value = false
}

onMounted(loadReviews)
</script>

<template>
  <div>
    <p v-if="loading" class="text-sm text-gray-600">Memuat ulasan...</p>
    <p v-else-if="reviews.length === 0" class="text-sm text-gray-600">Belum ada ulasan.</p>

    <div v-else class="space-y-4">
      <div v-for="review in reviews" :key="review.id" class="card p-4">
        <div class="flex items-center justify-between">
          <p class="font-medium text-gray-900">{{ review.user.name }}</p>
          <ReviewStars :rating="review.rating" />
        </div>
        <p class="mt-1 text-xs text-gray-500">{{ new Date(review.created_at).toLocaleDateString('id-ID') }}</p>
        <p v-if="review.comment" class="mt-2 text-sm text-gray-700">{{ review.comment }}</p>

        <div v-if="review.mitra_reply" class="mt-3 rounded-xl bg-brand-sage/10 p-3 text-sm">
          <p class="font-medium text-gray-900">Balasan dari mitra</p>
          <p class="mt-1 text-gray-700">{{ review.mitra_reply }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
