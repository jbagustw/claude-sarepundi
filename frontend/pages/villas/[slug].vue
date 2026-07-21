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

    <div v-else-if="villa">
      <h1 class="text-2xl font-bold text-gray-900">{{ villa.name }}</h1>
      <p class="text-gray-600">{{ villa.address ? `${villa.address}, ` : '' }}{{ villa.city }}{{ villa.province ? `, ${villa.province}` : '' }}</p>

      <div v-if="villa.images.length" class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <img
          v-for="image in villa.images"
          :key="image.id"
          :src="image.url"
          class="h-32 w-full rounded object-cover"
          alt=""
        >
      </div>

      <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-700">
        <span>{{ villa.capacity_guest }} tamu</span>
        <span>{{ villa.bedroom_count }} kamar tidur</span>
        <span>{{ villa.bathroom_count }} kamar mandi</span>
      </div>

      <p class="mt-4 text-xl font-semibold text-gray-900">{{ formatRupiah(villa.base_price) }} / malam</p>

      <p v-if="villa.description" class="mt-4 whitespace-pre-line text-gray-700">{{ villa.description }}</p>

      <div v-if="villa.facilities.length" class="mt-4">
        <h2 class="font-semibold text-gray-900">Fasilitas</h2>
        <div class="mt-2 flex flex-wrap gap-2">
          <span
            v-for="facility in villa.facilities"
            :key="facility.id"
            class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700"
          >
            {{ facility.name }}
          </span>
        </div>
      </div>

      <p class="mt-4 text-sm text-gray-500">Dikelola oleh {{ villa.mitra.business_name }}</p>
    </div>
  </div>
</template>
