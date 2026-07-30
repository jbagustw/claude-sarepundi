<script setup lang="ts">
import type { MitraStats } from '~/types/mitraStats'

definePageMeta({ role: 'mitra' })

const authStore = useAuthStore()
const api = useApi()
const mitraStatus = computed(() => authStore.user?.mitra_profile?.status ?? 'pending')

const statusLabel: Record<string, string> = {
  pending: 'Menunggu persetujuan admin',
  approved: 'Disetujui',
  rejected: 'Ditolak',
}

const statusColor: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
}

const stats = ref<MitraStats | null>(null)
const loading = ref(true)

async function loadStats() {
  loading.value = true
  const response = await api<{ data: MitraStats }>('/api/mitra/stats')
  stats.value = response.data
  loading.value = false
}

onMounted(loadStats)
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-gray-900">
      Dashboard Mitra — {{ authStore.user?.mitra_profile?.business_name }}
    </h1>

    <span
      class="mt-3 inline-block rounded px-3 py-1 text-sm font-medium"
      :class="statusColor[mitraStatus]"
    >
      {{ statusLabel[mitraStatus] }}
    </span>

    <p v-if="mitraStatus === 'pending'" class="mt-4 text-gray-600">
      Akun mitra kamu masih menunggu persetujuan admin. Kamu tetap bisa menyiapkan
      draft villa, tapi belum bisa dikirim untuk direview sampai akun disetujui.
    </p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat statistik...</p>

    <div v-else-if="stats" class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
      <div class="card p-4">
        <p class="text-xs text-gray-500">Pendapatan (setelah komisi)</p>
        <p class="mt-1 text-xl font-semibold text-gray-900">{{ formatRupiah(stats.total_pendapatan) }}</p>
        <p class="text-xs text-gray-400">dari booking selesai</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-500">Menunggu Konfirmasi</p>
        <p class="mt-1 text-xl font-semibold text-gray-900">{{ stats.booking_counts.menunggu_konfirmasi ?? 0 }}</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-500">Villa Dipublikasikan</p>
        <p class="mt-1 text-xl font-semibold text-gray-900">{{ stats.published_villas }}</p>
        <p class="text-xs text-gray-400">dari {{ stats.total_villas }} total</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-500">Occupancy Rate (bulan ini)</p>
        <p class="mt-1 text-xl font-semibold text-gray-900">{{ stats.occupancy_rate }}%</p>
      </div>
    </div>

    <div class="mt-6 flex gap-3">
      <NuxtLink to="/mitra/villas" class="btn-primary">
        Kelola Villa
      </NuxtLink>
      <NuxtLink to="/mitra/homestays" class="btn-primary">
        Kelola Homestay
      </NuxtLink>
      <NuxtLink to="/mitra/bookings" class="btn-outline">
        Kelola Booking
      </NuxtLink>
      <NuxtLink to="/mitra/payouts" class="btn-outline">
        Laporan Payout
      </NuxtLink>
      <NuxtLink to="/mitra/reviews" class="btn-outline">
        Ulasan
      </NuxtLink>
      <NuxtLink to="/mitra/profile" class="btn-outline">
        Profil Bisnis
      </NuxtLink>
    </div>
  </div>
</template>
