<script setup lang="ts">
import type { Homestay } from '~/types/homestay'

const route = useRoute()
const api = useApi()

const homestay = ref<Homestay | null>(null)
const notFound = ref(false)

onMounted(async () => {
  try {
    const response = await api<{ data: Homestay }>(`/api/homestays/${route.params.slug}`)
    homestay.value = response.data
  } catch {
    notFound.value = true
  }
})
</script>

<template>
  <div>
    <p v-if="notFound" class="text-gray-600">Homestay tidak ditemukan.</p>

    <div v-else-if="homestay" class="grid gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <h1 class="font-display text-2xl font-bold text-gray-900">{{ homestay.name }}</h1>
        <p class="text-gray-600">{{ homestay.address ? `${homestay.address}, ` : '' }}{{ homestay.city }}{{ homestay.province ? `, ${homestay.province}` : '' }}</p>

        <div v-if="homestay.images.length" class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
          <img
            v-for="image in homestay.images"
            :key="image.id"
            :src="image.url"
            class="h-32 w-full rounded-xl object-cover"
            alt=""
          >
        </div>

        <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-700">
          <span>{{ homestay.capacity_guest }} tamu</span>
          <span>{{ homestay.bedroom_count }} kamar tidur</span>
          <span>{{ homestay.bathroom_count }} kamar mandi</span>
        </div>

        <p v-if="homestay.description" class="mt-4 whitespace-pre-line text-gray-700">{{ homestay.description }}</p>

        <div v-if="homestay.facilities.length" class="mt-4">
          <h2 class="font-display font-semibold text-gray-900">Fasilitas</h2>
          <div class="mt-2 flex flex-wrap gap-2">
            <span
              v-for="facility in homestay.facilities"
              :key="facility.id"
              class="chip"
            >
              {{ facility.name }}
            </span>
          </div>
        </div>

        <p class="mt-4 text-sm text-gray-500">Dikelola oleh {{ homestay.mitra.business_name }}</p>
      </div>

      <div>
        <div class="card sticky top-4 p-4">
          <p class="text-lg font-semibold text-gray-900">
            {{ formatRupiah(homestay.base_price) }} <span class="text-sm font-normal text-gray-500">/ malam</span>
          </p>
          <p class="mt-3 rounded-xl bg-brand-sage/15 p-3 text-sm text-brand-brown-dark">
            Booking online untuk homestay segera hadir. Hubungi mitra melalui platform untuk info ketersediaan.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
