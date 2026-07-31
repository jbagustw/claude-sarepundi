<script setup lang="ts">
import type { Transport } from '~/types/transport'

const api = useApi()
const route = useRoute()
const transports = ref<Transport[]>([])
const loading = ref(true)

const filters = reactive({
  q: typeof route.query.q === 'string' ? route.query.q : '',
  city: typeof route.query.city === 'string' ? route.query.city : '',
  capacity: '',
  with_driver: false,
})

async function search() {
  loading.value = true
  const query: Record<string, string> = {}
  if (filters.q) query.q = filters.q
  if (filters.city) query.city = filters.city
  if (filters.capacity) query.capacity = filters.capacity
  if (filters.with_driver) query.with_driver = '1'

  const response = await api<{ data: Transport[] }>('/api/transports', { query })
  transports.value = response.data
  loading.value = false
}

onMounted(search)
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-gray-900">Cari Transport</h1>

    <form class="card mt-4 grid gap-3 p-4 sm:grid-cols-4" @submit.prevent="search">
      <input v-model="filters.q" type="text" placeholder="Cari nama kendaraan" class="field-input">
      <input v-model="filters.city" type="text" placeholder="Kota" class="field-input">
      <input v-model="filters.capacity" type="number" min="1" placeholder="Kapasitas minimal" class="field-input">

      <label class="flex items-center gap-2 text-sm text-gray-700">
        <input v-model="filters.with_driver" type="checkbox">
        Dengan supir saja
      </label>

      <button type="submit" class="btn-primary sm:col-span-4">
        Cari
      </button>
    </form>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="transports.length === 0" class="mt-6 text-gray-600">Tidak ada transport yang cocok dengan pencarian.</p>

    <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <NuxtLink
        v-for="transport in transports"
        :key="transport.id"
        :to="`/transports/${transport.slug}`"
        class="card block overflow-hidden transition hover:shadow-md"
      >
        <div class="relative">
          <img
            v-if="transport.images[0]"
            :src="transport.images[0].url"
            class="h-40 w-full object-cover"
            alt=""
          >
          <div v-else class="flex h-40 w-full items-center justify-center bg-gray-100 text-sm text-gray-400">
            Belum ada foto
          </div>
          <span class="badge absolute right-2 top-2 bg-black/60 text-white">{{ transport.vehicle_type }}</span>
        </div>
        <div class="bg-brand-brown/90 p-3 text-white">
          <h2 class="font-display font-semibold">{{ transport.name }}</h2>
          <p class="text-xs text-white/80">{{ transport.city }} &middot; {{ transport.capacity }} kursi</p>
          <p v-if="transport.price_per_day_self_drive" class="mt-1 text-xs">
            Lepas kunci {{ formatRupiah(transport.price_per_day_self_drive) }}/hari
          </p>
          <p v-if="transport.price_per_day_with_driver" class="text-xs">
            Dengan supir {{ formatRupiah(transport.price_per_day_with_driver) }}/hari
          </p>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
