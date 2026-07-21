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
    <h1 class="text-2xl font-bold text-gray-900">
      Admin Panel — {{ authStore.user?.name }}
    </h1>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat statistik...</p>

    <div v-else-if="stats" class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
      <div class="rounded border border-gray-200 bg-white p-4">
        <p class="text-xs text-gray-500">Menunggu Approval Mitra</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.mitras.pending_approval }}</p>
      </div>
      <div class="rounded border border-gray-200 bg-white p-4">
        <p class="text-xs text-gray-500">Villa Menunggu Review</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.villas.pending_review }}</p>
      </div>
      <div class="rounded border border-gray-200 bg-white p-4">
        <p class="text-xs text-gray-500">Booking Menunggu Konfirmasi</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.bookings.awaiting_mitra_confirmation }}</p>
      </div>
      <div class="rounded border border-gray-200 bg-white p-4">
        <p class="text-xs text-gray-500">Komisi Terkumpul</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ formatRupiah(stats.commission_earned) }}</p>
        <p class="text-xs text-gray-400">dari booking selesai</p>
      </div>
      <div class="rounded border border-gray-200 bg-white p-4">
        <p class="text-xs text-gray-500">Total User</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.users.total }}</p>
      </div>
      <div class="rounded border border-gray-200 bg-white p-4">
        <p class="text-xs text-gray-500">Total Mitra</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.users.total_mitra }}</p>
        <p class="text-xs text-gray-400">{{ stats.mitras.approved }} disetujui</p>
      </div>
      <div class="rounded border border-gray-200 bg-white p-4">
        <p class="text-xs text-gray-500">Villa Publikasi</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.villas.published }}</p>
        <p class="text-xs text-gray-400">dari {{ stats.villas.total }} total</p>
      </div>
      <div class="rounded border border-gray-200 bg-white p-4">
        <p class="text-xs text-gray-500">Total Booking</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.bookings.total }}</p>
        <p class="text-xs text-gray-400">{{ stats.bookings.completed }} selesai, {{ stats.bookings.cancelled }} dibatalkan</p>
      </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
      <NuxtLink to="/admin/mitras" class="inline-block rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700">
        Approval Mitra
      </NuxtLink>
      <NuxtLink to="/admin/villas" class="inline-block rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700">
        Moderasi Villa
      </NuxtLink>
      <NuxtLink to="/admin/transactions" class="inline-block rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
        Monitoring Transaksi
      </NuxtLink>
      <NuxtLink to="/admin/users" class="inline-block rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
        Kelola User & Mitra
      </NuxtLink>
    </div>
  </div>
</template>
