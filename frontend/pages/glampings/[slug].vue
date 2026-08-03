<script setup lang="ts">
import type { Glamping } from '~/types/glamping'

const route = useRoute()
const api = useApi()

const glamping = ref<Glamping | null>(null)
const notFound = ref(false)

onMounted(async () => {
  try {
    const response = await api<{ data: Glamping }>(`/api/glampings/${route.params.slug}`)
    glamping.value = response.data
  } catch {
    notFound.value = true
  }
})
</script>

<template>
  <div>
    <p v-if="notFound" class="text-gray-600">Glamping tidak ditemukan.</p>

    <div v-else-if="glamping" class="grid gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <h1 class="font-display text-2xl font-bold text-gray-900">{{ glamping.name }}</h1>
        <p class="text-gray-600">{{ glamping.address ? `${glamping.address}, ` : '' }}{{ glamping.city }}{{ glamping.province ? `, ${glamping.province}` : '' }}</p>

        <div v-if="glamping.reviews_count > 0" class="mt-1 flex items-center gap-2 text-sm">
          <ReviewStars :rating="glamping.reviews_avg_rating ?? 0" />
          <span class="text-gray-700">{{ glamping.reviews_avg_rating }} ({{ glamping.reviews_count }} ulasan)</span>
        </div>

        <ImageGallery :images="glamping.images" />

        <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-700">
          <span>{{ glamping.capacity_guest }} tamu</span>
          <span>{{ glamping.bedroom_count }} tenda</span>
          <span>{{ glamping.bathroom_count }} kamar mandi</span>
        </div>

        <div v-if="glamping.description" class="prose prose-sm mt-4 max-w-none text-gray-700" v-html="glamping.description" />

        <div v-if="glamping.facilities.length" class="mt-4">
          <h2 class="font-display font-semibold text-gray-900">Fasilitas</h2>
          <div class="mt-2 flex flex-wrap gap-2">
            <span
              v-for="facility in glamping.facilities"
              :key="facility.id"
              class="chip"
            >
              {{ facility.name }}
            </span>
          </div>
        </div>

        <p class="mt-4 text-sm text-gray-500">Dikelola oleh {{ glamping.mitra.business_name }}</p>

        <div class="mt-6">
          <h2 class="font-display font-semibold text-gray-900">Ulasan</h2>
          <div class="mt-3">
            <ReviewList resource-type="glamping" :resource-slug="glamping.slug" />
          </div>
        </div>
      </div>

      <div>
        <BookingWidget bookable-type="glamping" :bookable-slug="glamping.slug" :bookable-id="glamping.id" :base-price="glamping.base_price" />
      </div>
    </div>
  </div>
</template>
