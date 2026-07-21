<script setup lang="ts">
import type { Facility, Villa } from '~/types/villa'

const api = useApi()
const villas = ref<Villa[]>([])
const facilities = ref<Facility[]>([])
const loading = ref(true)

const filters = reactive({
  q: '',
  city: '',
  guests: '',
  min_price: '',
  max_price: '',
  facility_ids: [] as number[],
})

async function loadFacilities() {
  const response = await api<{ data: Facility[] }>('/api/facilities')
  facilities.value = response.data
}

async function search() {
  loading.value = true
  const query: Record<string, string | number[]> = {}
  if (filters.q) query.q = filters.q
  if (filters.city) query.city = filters.city
  if (filters.guests) query.guests = filters.guests
  if (filters.min_price) query.min_price = filters.min_price
  if (filters.max_price) query.max_price = filters.max_price
  if (filters.facility_ids.length) query.facility_ids = filters.facility_ids

  const response = await api<{ data: Villa[] }>('/api/villas', { query })
  villas.value = response.data
  loading.value = false
}

function toggleFacility(id: number) {
  const set = new Set(filters.facility_ids)
  if (set.has(id)) set.delete(id)
  else set.add(id)
  filters.facility_ids = Array.from(set)
}

onMounted(async () => {
  await Promise.all([loadFacilities(), search()])
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900">Cari Villa</h1>

    <form class="mt-4 grid gap-3 rounded border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="search">
      <input v-model="filters.q" type="text" placeholder="Cari nama villa" class="rounded border border-gray-300 px-3 py-2 text-sm">
      <input v-model="filters.city" type="text" placeholder="Kota" class="rounded border border-gray-300 px-3 py-2 text-sm">
      <input v-model="filters.guests" type="number" min="1" placeholder="Jumlah tamu" class="rounded border border-gray-300 px-3 py-2 text-sm">
      <div class="flex gap-2">
        <input v-model="filters.min_price" type="number" min="0" placeholder="Harga min" class="w-1/2 rounded border border-gray-300 px-3 py-2 text-sm">
        <input v-model="filters.max_price" type="number" min="0" placeholder="Harga maks" class="w-1/2 rounded border border-gray-300 px-3 py-2 text-sm">
      </div>

      <div class="sm:col-span-2 lg:col-span-4">
        <div class="flex flex-wrap gap-2">
          <label
            v-for="facility in facilities"
            :key="facility.id"
            class="flex items-center gap-1.5 rounded border border-gray-300 px-2 py-1 text-xs text-gray-700"
          >
            <input type="checkbox" :checked="filters.facility_ids.includes(facility.id)" @change="toggleFacility(facility.id)">
            {{ facility.name }}
          </label>
        </div>
      </div>

      <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700 sm:col-span-2 lg:col-span-4">
        Cari
      </button>
    </form>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="villas.length === 0" class="mt-6 text-gray-600">Tidak ada villa yang cocok dengan pencarian.</p>

    <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <NuxtLink
        v-for="villa in villas"
        :key="villa.id"
        :to="`/villas/${villa.slug}`"
        class="block overflow-hidden rounded border border-gray-200 bg-white hover:shadow"
      >
        <img
          v-if="villa.images[0]"
          :src="villa.images[0].url"
          class="h-40 w-full object-cover"
          alt=""
        >
        <div v-else class="flex h-40 w-full items-center justify-center bg-gray-100 text-sm text-gray-400">
          Belum ada foto
        </div>
        <div class="p-3">
          <h2 class="font-semibold text-gray-900">{{ villa.name }}</h2>
          <p class="text-sm text-gray-600">{{ villa.city }}</p>
          <p class="mt-1 text-sm font-medium text-gray-900">{{ formatRupiah(villa.base_price) }} / malam</p>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
