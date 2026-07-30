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
      </div>

      <div>
        <div class="card sticky top-4 p-4">
          <p class="font-display font-semibold text-gray-900">Slot & Harga</p>

          <p v-if="venue.slots.length === 0" class="mt-3 text-sm text-gray-500">
            Belum ada slot waktu yang tersedia untuk lokasi ini.
          </p>

          <ul v-else class="mt-3 space-y-2">
            <li
              v-for="slot in venue.slots"
              :key="slot.id"
              class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 text-sm"
            >
              <div>
                <p class="font-medium text-gray-900">{{ slot.name }}</p>
                <p class="text-xs text-gray-500">{{ slot.start_time }} - {{ slot.end_time }}</p>
              </div>
              <p class="font-semibold text-gray-900">{{ formatRupiah(slot.price) }}</p>
            </li>
          </ul>

          <p class="mt-3 rounded-xl bg-brand-sage/15 p-3 text-sm text-brand-brown-dark">
            Booking online segera hadir. Hubungi mitra melalui platform untuk info ketersediaan.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
