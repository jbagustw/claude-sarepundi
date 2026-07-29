<script setup lang="ts">
import type { Facility, Villa } from '~/types/villa'

const authStore = useAuthStore()
const api = useApi()
const router = useRouter()

const destination = ref('')
const guestCount = ref('')

const villas = ref<Villa[]>([])
const facilities = ref<Facility[]>([])
const loading = ref(true)

async function search() {
  const query: Record<string, string> = {}
  if (destination.value) query.q = destination.value
  if (guestCount.value) query.guests = guestCount.value
  router.push({ path: '/villas', query })
}

async function loadHomeData() {
  loading.value = true
  const [villasRes, facilitiesRes] = await Promise.all([
    api<{ data: Villa[] }>('/api/villas'),
    api<{ data: Facility[] }>('/api/facilities'),
  ])
  villas.value = villasRes.data.slice(0, 6)
  facilities.value = facilitiesRes.data
  loading.value = false
}

onMounted(loadHomeData)
</script>

<template>
  <div class="-mx-4 -mt-8">
    <section class="relative overflow-hidden bg-gradient-to-br from-brand-brown-dark via-brand-brown to-brand-terracotta px-4 pb-24 pt-14 sm:pb-28 sm:pt-20">
      <div
        class="absolute inset-0 bg-cover bg-center"
        style="background-image: url('/images/hero-banner.jpg')"
        aria-hidden="true"
      />
      <div class="absolute inset-0 bg-gradient-to-br from-brand-brown-dark/85 via-brand-brown/75 to-brand-terracotta/70" />
      <div class="pointer-events-none absolute inset-0 opacity-30">
        <div class="absolute -left-16 -top-16 h-72 w-72 rounded-full bg-brand-gold blur-3xl" />
        <div class="absolute -right-10 bottom-0 h-72 w-72 rounded-full bg-brand-sage blur-3xl" />
      </div>

      <div class="relative z-10 mx-auto max-w-3xl text-center">
        <h1 class="font-display text-3xl font-bold text-white sm:text-4xl">
          Cari &amp; Booking Villa Impianmu
        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-white/80 sm:text-base">
          Marketplace booking villa/penginapan — temukan villa terbaik untuk liburanmu selanjutnya.
        </p>

        <template v-if="!authStore.isAuthenticated">
          <div class="mt-6 flex justify-center gap-3">
            <NuxtLink to="/register" class="btn-accent">Daftar Sekarang</NuxtLink>
            <NuxtLink to="/login" class="btn-outline border-white/60 text-white hover:bg-white/10">Masuk</NuxtLink>
          </div>
        </template>
      </div>

      <form
        class="card relative z-10 mx-auto mt-10 flex max-w-3xl flex-col gap-3 rounded-3xl p-4 sm:flex-row sm:items-center sm:p-3"
        @submit.prevent="search"
      >
        <div class="flex flex-1 items-center gap-2 px-2">
          <span aria-hidden="true" class="text-gray-400">🔍</span>
          <input
            v-model="destination"
            type="text"
            placeholder="Ke mana selanjutnya? (nama villa atau kota)"
            class="w-full border-none bg-transparent py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-0"
          >
        </div>
        <div class="flex items-center gap-2 border-t border-gray-100 px-2 pt-3 sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0">
          <span aria-hidden="true" class="text-gray-400">👤</span>
          <input
            v-model="guestCount"
            type="number"
            min="1"
            placeholder="Jumlah tamu"
            class="w-28 border-none bg-transparent py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-0"
          >
        </div>
        <button type="submit" class="btn-accent !rounded-full !p-3 sm:!p-3.5" aria-label="Cari villa">
          🔍
        </button>
      </form>
    </section>

    <section class="mx-auto mt-10 max-w-6xl px-4">
      <h2 class="font-display text-xl font-bold text-gray-900">Temukan Penginapan Sesuai Keinginan Anda</h2>

      <p v-if="loading" class="mt-6 text-gray-600">Memuat villa...</p>
      <p v-else-if="villas.length === 0" class="mt-6 text-gray-600">Belum ada villa yang dipublikasikan.</p>

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
              class="h-44 w-full object-cover"
              alt=""
            >
            <div v-else class="flex h-44 w-full items-center justify-center bg-gray-100 text-sm text-gray-400">
              Belum ada foto
            </div>
            <span class="badge absolute right-2 top-2 bg-black/60 text-white">{{ formatRupiah(villa.base_price) }}</span>
          </div>
          <div class="bg-brand-brown/90 p-3 text-white">
            <p class="font-display font-semibold">{{ villa.name }}</p>
            <p class="text-xs text-white/80">{{ villa.city }}</p>
            <p v-if="villa.reviews_count > 0" class="mt-1 flex items-center gap-1 text-xs">
              <span class="text-brand-gold">★</span> {{ villa.reviews_avg_rating }}
            </p>
          </div>
        </NuxtLink>
      </div>

      <div class="mt-6 text-center">
        <NuxtLink to="/villas" class="btn-outline">Lihat Semua Villa</NuxtLink>
      </div>
    </section>

    <section v-if="facilities.length" class="mx-auto mt-12 max-w-6xl px-4">
      <h2 class="font-display text-xl font-bold text-gray-900">Pilih Fasilitas Sesuai Keinginan Anda</h2>
      <div class="mt-4 flex flex-wrap gap-2">
        <NuxtLink
          v-for="facility in facilities"
          :key="facility.id"
          :to="{ path: '/villas', query: { facility_ids: [facility.id] } }"
          class="chip"
        >
          {{ facility.name }}
        </NuxtLink>
      </div>
    </section>

    <section class="mx-auto mt-12 max-w-6xl px-4 pb-4">
      <div class="rounded-2xl bg-brand-footer px-6 py-10 text-center text-white sm:px-12">
        <p class="text-xs uppercase tracking-widest text-white/60">Newsletter</p>
        <h2 class="mt-2 font-display text-xl font-bold sm:text-2xl">Dapatkan Promo Rahasia Anda!</h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-white/70">
          Berlangganan newsletter kami dan jadilah yang pertama menerima diskon eksklusif dan info properti terbaru.
        </p>
        <form class="mx-auto mt-5 flex max-w-md gap-2" @submit.prevent>
          <input
            type="email"
            disabled
            title="Newsletter segera hadir"
            placeholder="Masukkan Emailmu"
            class="w-full rounded-full border-0 px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 disabled:cursor-not-allowed disabled:opacity-70"
          >
          <button type="submit" disabled title="Newsletter segera hadir" class="btn-primary shrink-0 disabled:opacity-70">
            Subscribe
          </button>
        </form>
      </div>
    </section>
  </div>
</template>
