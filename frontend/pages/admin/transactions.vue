<script setup lang="ts">
import type { AdminBooking } from '~/types/admin'

definePageMeta({ role: 'admin' })

const api = useApi()
const bookings = ref<AdminBooking[]>([])
const loading = ref(true)
const statusFilter = ref('')
const search = ref('')

const statusOptions = [
  { value: '', label: 'Semua Status' },
  { value: 'pending_payment', label: 'Menunggu Pembayaran' },
  { value: 'menunggu_konfirmasi', label: 'Menunggu Konfirmasi' },
  { value: 'dikonfirmasi', label: 'Dikonfirmasi' },
  { value: 'dibatalkan_mitra', label: 'Dibatalkan Mitra' },
  { value: 'dibatalkan_user', label: 'Dibatalkan User' },
  { value: 'checked_in', label: 'Check-in' },
  { value: 'selesai', label: 'Selesai' },
]

async function loadBookings() {
  loading.value = true
  const query: Record<string, string> = {}
  if (statusFilter.value) query.status = statusFilter.value
  if (search.value) query.search = search.value

  const response = await api<{ data: AdminBooking[] }>('/api/admin/bookings', { query })
  bookings.value = response.data
  loading.value = false
}

onMounted(loadBookings)
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900">Monitoring Transaksi</h1>

    <form class="mt-4 flex flex-wrap gap-3" @submit.prevent="loadBookings">
      <input
        v-model="search"
        type="text"
        placeholder="Cari kode booking, villa, atau user"
        class="min-w-[240px] flex-1 rounded border border-gray-300 px-3 py-2 text-sm"
      >
      <select v-model="statusFilter" class="rounded border border-gray-300 px-3 py-2 text-sm">
        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700">
        Filter
      </button>
    </form>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="bookings.length === 0" class="mt-6 text-gray-600">Tidak ada transaksi yang cocok.</p>

    <div v-else class="mt-6 overflow-x-auto rounded border border-gray-200 bg-white">
      <table class="min-w-full text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-3 py-2">Kode</th>
            <th class="px-3 py-2">Villa / Mitra</th>
            <th class="px-3 py-2">User</th>
            <th class="px-3 py-2">Tanggal</th>
            <th class="px-3 py-2 text-right">Total</th>
            <th class="px-3 py-2 text-right">Komisi</th>
            <th class="px-3 py-2">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="booking in bookings" :key="booking.id">
            <td class="px-3 py-2 font-mono text-xs">{{ booking.booking_code }}</td>
            <td class="px-3 py-2">
              <p class="text-gray-900">{{ booking.villa.name }}</p>
              <p class="text-xs text-gray-500">{{ booking.mitra.business_name }}</p>
            </td>
            <td class="px-3 py-2">
              <p class="text-gray-900">{{ booking.user.name }}</p>
              <p class="text-xs text-gray-500">{{ booking.user.email }}</p>
            </td>
            <td class="px-3 py-2 text-xs text-gray-600">
              {{ booking.check_in_date }} &rarr; {{ booking.check_out_date }}
            </td>
            <td class="px-3 py-2 text-right text-gray-900">{{ formatRupiah(booking.total_price) }}</td>
            <td class="px-3 py-2 text-right text-gray-600">{{ formatRupiah(booking.commission_amount) }}</td>
            <td class="px-3 py-2">
              <span class="rounded px-2 py-0.5 text-xs font-medium" :class="BOOKING_STATUS_COLOR[booking.status]">
                {{ BOOKING_STATUS_LABEL[booking.status] }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
