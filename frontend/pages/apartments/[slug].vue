<script setup lang="ts">
import type { Apartment } from '~/types/apartment'

const route = useRoute()
const api = useApi()

const apartment = ref<Apartment | null>(null)
const notFound = ref(false)

onMounted(async () => {
  try {
    const response = await api<{ data: Apartment }>(`/api/apartments/${route.params.slug}`)
    apartment.value = response.data
  } catch {
    notFound.value = true
  }
})
</script>

<template>
  <div>
    <p v-if="notFound" class="text-gray-600">Apartment tidak ditemukan.</p>

    <div v-else-if="apartment" class="grid gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <h1 class="font-display text-2xl font-bold text-gray-900">{{ apartment.name }}</h1>
        <p class="text-gray-600">{{ apartment.address ? `${apartment.address}, ` : '' }}{{ apartment.city }}{{ apartment.province ? `, ${apartment.province}` : '' }}</p>

        <div v-if="apartment.reviews_count > 0" class="mt-1 flex items-center gap-2 text-sm">
          <ReviewStars :rating="apartment.reviews_avg_rating ?? 0" />
          <span class="text-gray-700">{{ apartment.reviews_avg_rating }} ({{ apartment.reviews_count }} ulasan)</span>
        </div>

        <ImageGallery :images="apartment.images" />

        <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-700">
          <span>{{ apartment.capacity_guest }} tamu</span>
          <span>{{ apartment.bedroom_count }} kamar tidur</span>
          <span>{{ apartment.bathroom_count }} kamar mandi</span>
        </div>

        <div v-if="apartment.description" class="prose prose-sm mt-4 max-w-none text-gray-700" v-html="apartment.description" />

        <div v-if="apartment.facilities.length" class="mt-4">
          <h2 class="font-display font-semibold text-gray-900">Fasilitas</h2>
          <div class="mt-2 flex flex-wrap gap-2">
            <span
              v-for="facility in apartment.facilities"
              :key="facility.id"
              class="chip"
            >
              {{ facility.name }}
            </span>
          </div>
        </div>

        <p class="mt-4 text-sm text-gray-500">Dikelola oleh {{ apartment.mitra.business_name }}</p>

        <div class="mt-6">
          <h2 class="font-display font-semibold text-gray-900">Ulasan</h2>
          <div class="mt-3">
            <ReviewList resource-type="apartment" :resource-slug="apartment.slug" />
          </div>
        </div>
      </div>

      <div>
        <BookingWidget bookable-type="apartment" :bookable-slug="apartment.slug" :bookable-id="apartment.id" :base-price="apartment.base_price" />
      </div>
    </div>
  </div>
</template>
