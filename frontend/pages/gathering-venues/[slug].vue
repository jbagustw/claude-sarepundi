<script setup lang="ts">
import type { GatheringVenue } from '~/types/gatheringVenue'

const route = useRoute()
const api = useApi()

const venue = ref<GatheringVenue | null>(null)
const notFound = ref(false)

onMounted(async () => {
  try {
    const response = await api<{ data: GatheringVenue }>(`/api/gathering-venues/${route.params.slug}`)
    venue.value = response.data
  } catch {
    notFound.value = true
  }
})
</script>

<template>
  <div>
    <p v-if="notFound" class="text-gray-600">Lokasi gathering tidak ditemukan.</p>

    <div v-else-if="venue" class="grid gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <h1 class="font-display text-2xl font-bold text-gray-900">{{ venue.name }}</h1>
        <p class="text-gray-600">{{ venue.address ? `${venue.address}, ` : '' }}{{ venue.city }}{{ venue.province ? `, ${venue.province}` : '' }}</p>

        <div v-if="venue.reviews_count > 0" class="mt-1 flex items-center gap-2 text-sm">
          <ReviewStars :rating="venue.reviews_avg_rating ?? 0" />
          <span class="text-gray-700">{{ venue.reviews_avg_rating }} ({{ venue.reviews_count }} ulasan)</span>
        </div>

        <div v-if="venue.images.length" class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
          <img
            v-for="image in venue.images"
            :key="image.id"
            :src="image.url"
            class="h-32 w-full rounded-xl object-cover"
            alt=""
          >
        </div>

        <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-700">
          <span>Kapasitas {{ venue.capacity }} orang</span>
        </div>

        <p v-if="venue.description" class="mt-4 whitespace-pre-line text-gray-700">{{ venue.description }}</p>

        <div v-if="venue.facilities.length" class="mt-4">
          <h2 class="font-display font-semibold text-gray-900">Fasilitas</h2>
          <div class="mt-2 flex flex-wrap gap-2">
            <span
              v-for="facility in venue.facilities"
              :key="facility.id"
              class="chip"
            >
              {{ facility.name }}
            </span>
          </div>
        </div>

        <p class="mt-4 text-sm text-gray-500">Dikelola oleh {{ venue.mitra.business_name }}</p>

        <div class="mt-6">
          <h2 class="font-display font-semibold text-gray-900">Ulasan</h2>
          <div class="mt-3">
            <ReviewList resource-type="gathering_venue" :resource-slug="venue.slug" />
          </div>
        </div>
      </div>

      <div>
        <BookingSlotWidget
          :venue-slug="venue.slug"
          :venue-id="venue.id"
          :capacity="venue.capacity"
          :starting-price="venue.starting_price"
        />
      </div>
    </div>
  </div>
</template>
