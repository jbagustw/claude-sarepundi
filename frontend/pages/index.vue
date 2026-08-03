<script setup lang="ts">
import type { Article } from '~/types/article'
import type { Banner } from '~/types/banner'
import type { Coupon } from '~/types/coupon'
import type { Facility, Villa } from '~/types/villa'
import type { Homestay } from '~/types/homestay'

const authStore = useAuthStore()
const api = useApi()
const router = useRouter()

const { data: siteSettings } = await useSiteSettings()
const heroImageUrl = computed(() => siteSettings.value?.hero_image_url || '/images/hero-banner.jpg')

type CategoryKey = 'villa' | 'homestay' | 'gathering_venue' | 'transport'

const categoryTabs: { key: CategoryKey; label: string; path: string; countLabel: string; countParam: 'guests' | 'capacity' }[] = [
  { key: 'villa', label: 'Villa', path: '/villas', countLabel: 'Jumlah Tamu', countParam: 'guests' },
  { key: 'homestay', label: 'Homestay', path: '/homestays', countLabel: 'Jumlah Tamu', countParam: 'guests' },
  { key: 'gathering_venue', label: 'Lokasi Gathering', path: '/gathering-venues', countLabel: 'Kapasitas', countParam: 'capacity' },
  { key: 'transport', label: 'Transport', path: '/transports', countLabel: 'Kapasitas', countParam: 'capacity' },
]

const activeTab = ref<CategoryKey>('villa')
const activeCategory = computed(() => categoryTabs.find(tab => tab.key === activeTab.value)!)

const destination = ref('')
const guestCount = ref('')

interface FeaturedListing {
  type: 'villa' | 'homestay'
  id: number
  name: string
  slug: string
  city: string
  base_price: number
  image: string | null
  reviews_avg_rating: number | null
  reviews_count: number
}

const coupons = ref<Coupon[]>([])
const banners = ref<Banner[]>([])
const featuredListings = ref<FeaturedListing[]>([])
const articles = ref<Article[]>([])
const facilities = ref<Facility[]>([])
const loading = ref(true)
const copiedCode = ref('')

const whyChooseUs = [
  {
    title: 'Beragam Pilihan',
    description: 'Villa, homestay, lokasi gathering, hingga transport — semua ada dalam satu platform.',
    icon: 'variety',
  },
  {
    title: 'Mitra Terverifikasi',
    description: 'Setiap mitra melalui proses persetujuan admin sebelum bisa menerima booking.',
    icon: 'verified',
  },
  {
    title: 'Pembayaran Aman',
    description: 'Transaksi diproses lewat payment gateway terpercaya, dana mitra baru cair setelah booking selesai.',
    icon: 'secure',
  },
  {
    title: 'Kebijakan Refund Jelas',
    description: 'Aturan pembatalan dan refund transparan, baik untuk pembatalan dari user maupun mitra.',
    icon: 'refund',
  },
]

const paymentPartners = [
  {
    title: 'Transfer Bank',
    icon: 'bank',
    methods: ['BCA', 'BNI', 'BRI', 'BSI', 'Mandiri', 'CIMB Niaga', 'Permata', 'Bank Sampoerna'],
  },
  {
    title: 'Kartu Kredit & Debit',
    icon: 'card',
    methods: ['Visa', 'Mastercard', 'JCB'],
  },
  {
    title: 'E-Wallet',
    icon: 'wallet',
    methods: ['OVO', 'DANA', 'LinkAja', 'ShopeePay', 'AstraPay', 'Jenius Pay'],
  },
  {
    title: 'QRIS',
    icon: 'qr',
    methods: ['Semua e-wallet & bank pendukung QRIS'],
  },
  {
    title: 'Gerai Retail',
    icon: 'store',
    methods: ['Alfamart', 'Indomaret'],
  },
  {
    title: 'PayLater',
    icon: 'paylater',
    methods: ['Kredivo', 'Atome', 'Indodana', 'Akulaku'],
  },
]

async function search() {
  const query: Record<string, string> = {}
  if (destination.value) query.q = destination.value
  if (guestCount.value) query[activeCategory.value.countParam] = guestCount.value
  router.push({ path: activeCategory.value.path, query })
}

async function copyCode(coupon: Coupon) {
  try {
    await navigator.clipboard.writeText(coupon.code)
    copiedCode.value = coupon.code
    setTimeout(() => {
      if (copiedCode.value === coupon.code) copiedCode.value = ''
    }, 2000)
  } catch {
    // Clipboard API can be unavailable (e.g. insecure context) — silently ignore.
  }
}

function discountLabel(coupon: Coupon): string {
  return coupon.discount_type === 'percentage' ? `${coupon.discount_value}%` : formatRupiah(coupon.discount_value)
}

async function loadHomeData() {
  loading.value = true
  const [villasRes, homestaysRes, facilitiesRes, articlesRes, couponsRes, bannersRes] = await Promise.all([
    api<{ data: Villa[] }>('/api/villas'),
    api<{ data: Homestay[] }>('/api/homestays'),
    api<{ data: Facility[] }>('/api/facilities'),
    api<{ data: Article[] }>('/api/articles'),
    api<{ data: Coupon[] }>('/api/coupons'),
    api<{ data: Banner[] }>('/api/banners'),
  ])

  const villaListings: FeaturedListing[] = villasRes.data.slice(0, 3).map(villa => ({
    type: 'villa',
    id: villa.id,
    name: villa.name,
    slug: villa.slug,
    city: villa.city,
    base_price: villa.base_price,
    image: villa.images[0]?.url ?? null,
    reviews_avg_rating: villa.reviews_avg_rating,
    reviews_count: villa.reviews_count,
  }))
  const homestayListings: FeaturedListing[] = homestaysRes.data.slice(0, 3).map(homestay => ({
    type: 'homestay',
    id: homestay.id,
    name: homestay.name,
    slug: homestay.slug,
    city: homestay.city,
    base_price: homestay.base_price,
    image: homestay.images[0]?.url ?? null,
    reviews_avg_rating: homestay.reviews_avg_rating,
    reviews_count: homestay.reviews_count,
  }))
  featuredListings.value = [...villaListings, ...homestayListings]

  facilities.value = facilitiesRes.data
  articles.value = articlesRes.data.slice(0, 3)
  coupons.value = couponsRes.data
  banners.value = bannersRes.data
  loading.value = false
}

onMounted(loadHomeData)
</script>

<template>
  <div class="-mt-8">
    <section class="relative left-1/2 right-1/2 -mx-[50vw] w-screen overflow-hidden bg-brand-navy px-4 pb-28 pt-32 sm:pb-36 sm:pt-44">
      <div
        class="absolute inset-0 bg-cover bg-center"
        :style="{ backgroundImage: `url('${heroImageUrl}')` }"
        aria-hidden="true"
      />
      <div class="absolute inset-0 bg-gradient-to-br from-brand-navy/90 via-brand-navy/75 to-brand-navy/60" />
      <div class="pointer-events-none absolute inset-0 opacity-30">
        <div class="absolute -left-16 -top-16 h-72 w-72 rounded-full bg-brand-gold blur-3xl" />
        <div class="absolute -right-10 bottom-0 h-72 w-72 rounded-full bg-brand-sage blur-3xl" />
      </div>

      <div class="relative z-10 mx-auto max-w-3xl text-center">
        <h1 class="font-display text-3xl font-bold text-white sm:text-4xl">
          Pilihan Utama untuk Jelajahi Nusantara
        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-white/80 sm:text-base">
          Villa, homestay, lokasi gathering, dan transport — temukan yang terbaik untuk liburan atau acaramu selanjutnya.
        </p>

        <template v-if="!authStore.isAuthenticated">
          <div class="mt-6 flex justify-center gap-3">
            <NuxtLink to="/register" class="btn-accent">Daftar Sekarang</NuxtLink>
            <NuxtLink to="/login" class="btn-outline border-white/60 text-white hover:bg-white/10">Masuk</NuxtLink>
          </div>
        </template>
      </div>
    </section>

    <div class="relative z-10 mx-auto -mt-16 max-w-3xl px-4 sm:-mt-20">
      <div class="flex gap-1 overflow-x-auto px-2">
        <button
          v-for="tab in categoryTabs"
          :key="tab.key"
          type="button"
          class="shrink-0 rounded-t-xl px-4 py-2 text-sm font-medium transition"
          :class="activeTab === tab.key ? 'bg-white text-brand-brown-dark' : 'bg-white/40 text-white hover:bg-white/60'"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <form
        class="card flex flex-col gap-3 rounded-3xl rounded-tl-none p-4 sm:flex-row sm:items-center sm:p-3"
        @submit.prevent="search"
      >
        <div class="flex flex-1 items-center gap-2 px-2">
          <span aria-hidden="true" class="text-gray-400">🔍</span>
          <input
            v-model="destination"
            type="text"
            placeholder="Ke mana selanjutnya? (nama atau kota)"
            class="w-full border-none bg-transparent py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-0"
          >
        </div>
        <div class="flex items-center gap-2 border-t border-gray-100 px-2 pt-3 sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0">
          <span aria-hidden="true" class="text-gray-400">👤</span>
          <input
            v-model="guestCount"
            type="number"
            min="1"
            :placeholder="activeCategory.countLabel"
            class="w-32 border-none bg-transparent py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-0"
          >
        </div>
        <button type="submit" class="btn-accent !rounded-full !p-3 sm:!p-3.5" :aria-label="`Cari ${activeCategory.label}`">
          🔍
        </button>
      </form>
    </div>

    <!-- Coupon -->
    <section v-if="coupons.length" class="mx-auto mt-10 max-w-6xl px-4">
      <h2 class="font-display text-xl font-bold text-gray-900">Kupon Promo Untukmu</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="coupon in coupons"
          :key="coupon.id"
          class="card flex items-center justify-between gap-3 border-l-4 border-l-brand-terracotta p-4"
        >
          <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-terracotta">Diskon {{ discountLabel(coupon) }}</p>
            <p class="mt-1 truncate font-display font-semibold text-gray-900">{{ coupon.title }}</p>
            <p v-if="coupon.description" class="mt-0.5 truncate text-xs text-gray-500">{{ coupon.description }}</p>
            <p class="mt-2 font-mono text-sm font-bold text-gray-800">{{ coupon.code }}</p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded-full border border-brand-brown/30 px-3 py-1.5 text-xs font-medium text-brand-brown transition hover:bg-brand-brown/5"
            @click="copyCode(coupon)"
          >
            {{ copiedCode === coupon.code ? 'Tersalin!' : 'Salin' }}
          </button>
        </div>
      </div>
    </section>

    <!-- Banner iklan -->
    <section v-if="banners.length" class="mx-auto mt-10 max-w-6xl px-4">
      <div class="space-y-4">
        <component
          :is="banner.link_url ? 'a' : 'div'"
          v-for="banner in banners"
          :key="banner.id"
          :href="banner.link_url ?? undefined"
          class="block overflow-hidden rounded-2xl"
          :class="{ 'transition hover:opacity-90': banner.link_url }"
        >
          <img :src="banner.image ?? ''" :alt="banner.title" class="h-40 w-full object-cover sm:h-64">
        </component>
      </div>
    </section>

    <!-- Featured listing (Villa + Homestay) -->
    <section class="mx-auto mt-12 max-w-6xl px-4">
      <h2 class="font-display text-xl font-bold text-gray-900">Temukan Penginapan Sesuai Keinginan Anda</h2>

      <p v-if="loading" class="mt-6 text-gray-600">Memuat listing...</p>
      <p v-else-if="featuredListings.length === 0" class="mt-6 text-gray-600">Belum ada villa atau homestay yang dipublikasikan.</p>

      <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <NuxtLink
          v-for="listing in featuredListings"
          :key="`${listing.type}-${listing.id}`"
          :to="`/${listing.type === 'homestay' ? 'homestays' : 'villas'}/${listing.slug}`"
          class="card block overflow-hidden transition hover:shadow-md"
        >
          <div class="relative">
            <img
              v-if="listing.image"
              :src="listing.image"
              class="h-44 w-full object-cover"
              alt=""
            >
            <div v-else class="flex h-44 w-full items-center justify-center bg-gray-100 text-sm text-gray-400">
              Belum ada foto
            </div>
            <span class="badge absolute left-2 top-2 bg-white/90 text-brand-brown-dark">
              {{ listing.type === 'homestay' ? 'Homestay' : 'Villa' }}
            </span>
            <span class="badge absolute right-2 top-2 bg-black/60 text-white">{{ formatRupiah(listing.base_price) }}</span>
          </div>
          <div class="bg-brand-brown/90 p-3 text-white">
            <p class="font-display font-semibold">{{ listing.name }}</p>
            <p class="text-xs text-white/80">{{ listing.city }}</p>
            <p v-if="listing.reviews_count > 0" class="mt-1 flex items-center gap-1 text-xs">
              <span class="text-brand-gold">★</span> {{ listing.reviews_avg_rating }}
            </p>
          </div>
        </NuxtLink>
      </div>

      <div class="mt-6 flex justify-center gap-3">
        <NuxtLink to="/villas" class="btn-outline">Lihat Semua Villa</NuxtLink>
        <NuxtLink to="/homestays" class="btn-outline">Lihat Semua Homestay</NuxtLink>
      </div>
    </section>

    <!-- Berita dan Artikel -->
    <section v-if="articles.length" class="mx-auto mt-12 max-w-6xl px-4">
      <div class="flex items-center justify-between">
        <h2 class="font-display text-xl font-bold text-gray-900">Berita dan Artikel</h2>
        <NuxtLink to="/berita" class="text-sm font-medium text-brand-brown hover:underline">Lihat Semua</NuxtLink>
      </div>

      <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <NuxtLink
          v-for="article in articles"
          :key="article.id"
          :to="`/berita/${article.slug}`"
          class="card block overflow-hidden transition hover:shadow-md"
        >
          <img
            v-if="article.cover_image"
            :src="article.cover_image"
            class="h-40 w-full object-cover"
            alt=""
          >
          <div v-else class="flex h-40 w-full items-center justify-center bg-gray-100 text-sm text-gray-400">
            Belum ada gambar
          </div>
          <div class="p-4">
            <span v-if="article.category" class="badge bg-brand-sage/20 text-brand-brown-dark">{{ article.category }}</span>
            <h3 class="mt-2 font-display font-semibold text-gray-900">{{ article.title }}</h3>
            <p v-if="article.excerpt" class="mt-1 line-clamp-2 text-sm text-gray-600">{{ article.excerpt }}</p>
          </div>
        </NuxtLink>
      </div>
    </section>

    <!-- Kenapa Memilih Sarepundi -->
    <section class="mx-auto mt-12 max-w-6xl px-4">
      <h2 class="font-display text-xl font-bold text-gray-900">Kenapa Memilih Sarepundi</h2>

      <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="reason in whyChooseUs" :key="reason.title" class="card p-5">
          <div class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-sage/15 text-brand-brown-dark">
            <svg
              v-if="reason.icon === 'variety'"
              class="h-6 w-6"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M4 21V9l8-6 8 6v12" />
              <path d="M9 21v-6h6v6" />
            </svg>
            <svg
              v-else-if="reason.icon === 'verified'"
              class="h-6 w-6"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z" />
              <path d="m9 12 2 2 4-4" />
            </svg>
            <svg
              v-else-if="reason.icon === 'secure'"
              class="h-6 w-6"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <rect x="4" y="10.5" width="16" height="9.5" rx="2" />
              <path d="M8 10.5V7a4 4 0 0 1 8 0v3.5" />
            </svg>
            <svg
              v-else
              class="h-6 w-6"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M3 12a9 9 0 1 1 3.5 7.1L3 20l1-3.4A8.96 8.96 0 0 1 3 12Z" />
              <path d="M9 12h6M9 15h4" />
            </svg>
          </div>
          <p class="mt-3 font-display font-semibold text-gray-900">{{ reason.title }}</p>
          <p class="mt-1 text-sm text-gray-600">{{ reason.description }}</p>
        </div>
      </div>
    </section>

    <!-- Mitra Pembayaran -->
    <section class="mx-auto mt-12 max-w-6xl px-4">
      <h2 class="font-display text-xl font-bold text-gray-900">Mitra Pembayaran</h2>
      <p class="mt-1 text-sm text-gray-600">
        Sarepundi didukung berbagai metode pembayaran populer di Indonesia.
      </p>

      <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="partner in paymentPartners" :key="partner.title" class="card p-5">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-navy/10 text-brand-navy">
              <svg
                v-if="partner.icon === 'bank'"
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M2 9l10-6 10 6" />
                <path d="M4 9v10M8 9v10M12 9v10M16 9v10M20 9v10" />
                <path d="M2 21h20" />
              </svg>
              <svg
                v-else-if="partner.icon === 'card'"
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <rect x="2" y="6" width="20" height="14" rx="2" />
                <path d="M2 10h20" />
              </svg>
              <svg
                v-else-if="partner.icon === 'wallet'"
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                <rect x="13" y="11" width="8" height="6" rx="1" />
                <circle cx="16.5" cy="14" r="0.8" fill="currentColor" stroke="none" />
              </svg>
              <svg
                v-else-if="partner.icon === 'qr'"
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <rect x="3" y="3" width="7" height="7" rx="1" />
                <rect x="14" y="3" width="7" height="7" rx="1" />
                <rect x="3" y="14" width="7" height="7" rx="1" />
                <path d="M14 14h3v3h-3zM19 14h2v2h-2zM14 19h2v2h-2zM19 19h2v2h-2z" fill="currentColor" stroke="none" />
              </svg>
              <svg
                v-else-if="partner.icon === 'store'"
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M3 9l1-5h16l1 5" />
                <path d="M4 9v10a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9" />
                <path d="M9 20v-6h6v6" />
              </svg>
              <svg
                v-else
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <circle cx="12" cy="13" r="8" />
                <path d="M12 9v4l3 2" />
                <path d="M9 2h6" />
              </svg>
            </div>
            <p class="font-display font-semibold text-gray-900">{{ partner.title }}</p>
          </div>
          <div class="mt-3 flex flex-wrap gap-1.5">
            <span v-for="method in partner.methods" :key="method" class="chip !cursor-default hover:!bg-white">
              {{ method }}
            </span>
          </div>
        </div>
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
