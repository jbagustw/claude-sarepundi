<script setup lang="ts">
import type { Transport } from '~/types/transport'

const route = useRoute()
const api = useApi()

const transport = ref<Transport | null>(null)
const notFound = ref(false)

const startingPrice = computed(() => {
  if (!transport.value) return 0
  const prices = [transport.value.price_per_day_self_drive, transport.value.price_per_day_with_driver].filter(
    (p): p is number => p !== null
  )
  return prices.length ? Math.min(...prices) : 0
})

onMounted(async () => {
  try {
    const response = await api<{ data: Transport }>(`/api/transports/${route.params.slug}`)
    transport.value = response.data
  } catch {
    notFound.value = true
  }
})
</script>

<template>
  <div>
    <p v-if="notFound" class="text-gray-600">Transport tidak ditemukan.</p>

    <div v-else-if="transport" class="grid gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <h1 class="font-display text-2xl font-bold text-gray-900">{{ transport.name }}</h1>
        <p class="text-gray-600">{{ transport.vehicle_type }} &middot; {{ transport.city }}{{ transport.province ? `, ${transport.province}` : '' }}</p>

        <div v-if="transport.reviews_count > 0" class="mt-1 flex items-center gap-2 text-sm">
          <ReviewStars :rating="transport.reviews_avg_rating ?? 0" />
          <span class="text-gray-700">{{ transport.reviews_avg_rating }} ({{ transport.reviews_count }} ulasan)</span>
        </div>

        <div v-if="transport.images.length" class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
          <img
            v-for="image in transport.images"
            :key="image.id"
            :src="image.url"
            class="h-32 w-full rounded-xl object-cover"
            alt=""
          >
        </div>

        <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-700">
          <span>Kapasitas {{ transport.capacity }} kursi</span>
        </div>

        <p v-if="transport.description" class="mt-4 whitespace-pre-line text-gray-700">{{ transport.description }}</p>

        <p class="mt-4 text-sm text-gray-500">Dikelola oleh {{ transport.mitra.business_name }}</p>

        <div class="mt-6">
          <h2 class="font-display font-semibold text-gray-900">Ulasan</h2>
          <div class="mt-3">
            <ReviewList resource-type="transport" :resource-slug="transport.slug" />
          </div>
        </div>
      </div>

      <div>
        <BookingWidget
          bookable-type="transport"
          :bookable-slug="transport.slug"
          :bookable-id="transport.id"
          :base-price="startingPrice"
          :transport-pricing="{ selfDrive: transport.price_per_day_self_drive, withDriver: transport.price_per_day_with_driver }"
        />
      </div>
    </div>
  </div>
</template>
