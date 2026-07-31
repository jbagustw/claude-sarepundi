<script setup lang="ts">
import type { Transport } from '~/types/transport'

const route = useRoute()
const api = useApi()

const transport = ref<Transport | null>(null)
const notFound = ref(false)

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
      </div>

      <div>
        <div class="card sticky top-4 p-4">
          <p class="font-display font-semibold text-gray-900">Harga Sewa per Hari</p>

          <div class="mt-3 space-y-2">
            <div v-if="transport.price_per_day_self_drive" class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 text-sm">
              <p class="font-medium text-gray-900">Lepas Kunci</p>
              <p class="font-semibold text-gray-900">{{ formatRupiah(transport.price_per_day_self_drive) }}</p>
            </div>
            <div v-if="transport.price_per_day_with_driver" class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 text-sm">
              <p class="font-medium text-gray-900">Dengan Supir</p>
              <p class="font-semibold text-gray-900">{{ formatRupiah(transport.price_per_day_with_driver) }}</p>
            </div>
          </div>

          <p class="mt-3 rounded-xl bg-brand-sage/15 p-3 text-sm text-brand-brown-dark">
            Booking online segera hadir. Hubungi mitra melalui platform untuk info ketersediaan.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
