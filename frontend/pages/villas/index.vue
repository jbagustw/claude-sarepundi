<script setup lang="ts">
import type { Facility, Villa } from '~/types/villa'

const api = useApi()
const route = useRoute()
const villas = ref<Villa[]>([])
const facilities = ref<Facility[]>([])
const loading = ref(true)

function toArray(value: unknown): string[] {
  if (Array.isArray(value)) return value.map(String)
  if (value === undefined || value === null) return []
  return [String(value)]
}

const filters = reactive({
  q: typeof route.query.q === 'string' ? route.query.q : '',
  city: typeof route.query.city === 'string' ? route.query.city : '',
  guests: typeof route.query.guests === 'string' ? route.query.guests : '',
  min_price: '',
  max_price: '',
  facility_ids: toArray(route.query.facility_ids).map(Number),
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
    <h1 class="font-display text-2xl font-bold text-gray-900">Cari Villa</h1>

    <form class="card mt-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="search">
      <input v-model="filters.q" type="text" placeholder="Cari nama villa" class="field-input">
      <input v-model="filters.city" type="text" placeholder="Kota" class="field-input">
      <input v-model="filters.guests" type="number" min="1" placeholder="Jumlah tamu" class="field-input">
      <div class="flex gap-2">
        <input v-model="filters.min_price" type="number" min="0" placeholder="Harga min" class="field-input w-1/2">
        <input v-model="filters.max_price" type="number" min="0" placeholder="Harga maks" class="field-input w-1/2">
      </div>

      <div class="sm:col-span-2 lg:col-span-4">
        <div class="flex flex-wrap gap-2">
          <label
            v-for="facility in facilities"
            :key="facility.id"
            class="flex cursor-pointer items-center gap-1.5"
            :class="filters.facility_ids.includes(facility.id) ? 'chip-active' : 'chip'"
          >
            <input
              type="checkbox"
              class="hidden"
              :checked="filters.facility_ids.includes(facility.id)"
              @change="toggleFacility(facility.id)"
            >
            {{ facility.name }}
          </label>
        </div>
      </div>

      <button type="submit" class="btn-primary sm:col-span-2 lg:col-span-4">
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
        class="card block overflow-hidden transition hover:shadow-md"
      >
        <div class="relative">
          <img
            v-if="villa.images[0]"
            :src="villa.images[0].url"
            class="h-40 w-full object-cover"
            alt=""
          >
          <div v-else class="flex h-40 w-full items-center justify-center bg-gray-100 text-sm text-gray-400">
            Belum ada foto
          </div>
          <span class="badge absolute right-2 top-2 bg-black/60 text-white">{{ formatRupiah(villa.base_price) }}</span>
        </div>
        <div class="bg-brand-brown/90 p-3 text-white">
          <h2 class="font-display font-semibold">{{ villa.name }}</h2>
          <p class="text-xs text-white/80">{{ villa.city }}</p>
          <div v-if="villa.reviews_count > 0" class="mt-1 flex items-center gap-1 text-xs">
            <span class="text-brand-gold">★</span> {{ villa.reviews_avg_rating }} <span class="text-white/70">({{ villa.reviews_count }})</span>
          </div>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
