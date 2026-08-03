<script setup lang="ts">
import type { Villa } from '~/types/villa'

const route = useRoute()
const api = useApi()

const villa = ref<Villa | null>(null)
const notFound = ref(false)

onMounted(async () => {
  try {
    const response = await api<{ data: Villa }>(`/api/villas/${route.params.slug}`)
    villa.value = response.data
  } catch {
    notFound.value = true
  }
})
</script>

<template>
  <div>
    <p v-if="notFound" class="text-gray-600">Villa tidak ditemukan.</p>

    <div v-else-if="villa" class="grid gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <h1 class="font-display text-2xl font-bold text-gray-900">{{ villa.name }}</h1>
        <p class="text-gray-600">{{ villa.address ? `${villa.address}, ` : '' }}{{ villa.city }}{{ villa.province ? `, ${villa.province}` : '' }}</p>

        <div v-if="villa.reviews_count > 0" class="mt-1 flex items-center gap-2 text-sm">
          <ReviewStars :rating="villa.reviews_avg_rating ?? 0" />
          <span class="text-gray-700">{{ villa.reviews_avg_rating }} ({{ villa.reviews_count }} ulasan)</span>
        </div>

        <ImageGallery :images="villa.images" />

        <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-700">
          <span>{{ villa.capacity_guest }} tamu</span>
          <span>{{ villa.bedroom_count }} kamar tidur</span>
          <span>{{ villa.bathroom_count }} kamar mandi</span>
        </div>

        <div v-if="villa.description" class="prose prose-sm mt-4 max-w-none text-gray-700" v-html="villa.description" />

        <div v-if="villa.facilities.length" class="mt-4">
          <h2 class="font-display font-semibold text-gray-900">Fasilitas</h2>
          <div class="mt-2 flex flex-wrap gap-2">
            <span
              v-for="facility in villa.facilities"
              :key="facility.id"
              class="chip"
            >
              {{ facility.name }}
            </span>
          </div>
        </div>

        <p class="mt-4 text-sm text-gray-500">Dikelola oleh {{ villa.mitra.business_name }}</p>

        <div class="mt-6">
          <h2 class="font-display font-semibold text-gray-900">Ulasan</h2>
          <div class="mt-3">
            <ReviewList resource-type="villa" :resource-slug="villa.slug" />
          </div>
        </div>
      </div>

      <div>
        <BookingWidget bookable-type="villa" :bookable-slug="villa.slug" :bookable-id="villa.id" :base-price="villa.base_price" />
      </div>
    </div>
  </div>
</template>
