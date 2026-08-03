<script setup lang="ts">
import type { AdminStats } from '~/types/admin'

definePageMeta({ role: 'admin' })

const authStore = useAuthStore()
const api = useApi()

const stats = ref<AdminStats | null>(null)
const loading = ref(true)

async function loadStats() {
  loading.value = true
  const response = await api<{ data: AdminStats }>('/api/admin/stats')
  stats.value = response.data
  loading.value = false
}

onMounted(loadStats)
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-gray-900">
      Admin Panel — {{ authStore.user?.name }}
    </h1>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat statistik...</p>

    <div v-else-if="stats" class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
      <div class="card p-4">
        <p class="text-xs text-gray-500">Menunggu Approval Mitra</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.mitras.pending_approval }}</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-500">Villa Menunggu Review</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.villas.pending_review }}</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-500">Booking Menunggu Pembayaran</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.bookings.awaiting_payment }}</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-500">Komisi Terkumpul</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ formatRupiah(stats.commission_earned) }}</p>
        <p class="text-xs text-gray-400">dari booking selesai</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-500">Total User</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.users.total }}</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-500">Total Mitra</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.users.total_mitra }}</p>
        <p class="text-xs text-gray-400">{{ stats.mitras.approved }} disetujui</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-500">Villa Publikasi</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.villas.published }}</p>
        <p class="text-xs text-gray-400">dari {{ stats.villas.total }} total</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-500">Total Booking</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.bookings.total }}</p>
        <p class="text-xs text-gray-400">{{ stats.bookings.completed }} selesai, {{ stats.bookings.cancelled }} dibatalkan</p>
      </div>
    </div>
  </div>
</template>
