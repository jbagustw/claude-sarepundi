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
    <h1 class="font-display text-2xl font-bold text-gray-900">Monitoring Transaksi</h1>

    <form class="mt-4 flex flex-wrap gap-3" @submit.prevent="loadBookings">
      <input
        v-model="search"
        type="text"
        placeholder="Cari kode booking, nama listing, atau user"
        class="field-input min-w-[240px] flex-1"
      >
      <select v-model="statusFilter" class="field-input">
        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <button type="submit" class="btn-primary">
        Filter
      </button>
    </form>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="bookings.length === 0" class="mt-6 text-gray-600">Tidak ada transaksi yang cocok.</p>

    <div v-else class="card mt-6 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-3 py-2">Kode</th>
            <th class="px-3 py-2">Listing / Mitra</th>
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
              <p class="text-gray-900">{{ booking.bookable.name }}</p>
              <p class="text-xs text-gray-500">{{ booking.mitra.business_name }}</p>
            </td>
            <td class="px-3 py-2">
              <p class="text-gray-900">{{ booking.user.name }}</p>
              <p class="text-xs text-gray-500">{{ booking.user.email }}</p>
            </td>
            <td class="px-3 py-2 text-xs text-gray-600">
              <template v-if="booking.bookable.type === 'gathering_venue'">
                {{ booking.check_in_date }}<span v-if="booking.slot"> &middot; {{ booking.slot.start_time }}-{{ booking.slot.end_time }}</span>
              </template>
              <template v-else>
                {{ booking.check_in_date }} &rarr; {{ booking.check_out_date }}
                <span v-if="booking.bookable.type === 'transport'">&middot; {{ booking.transport_with_driver ? 'Sopir' : 'Lepas Kunci' }}</span>
              </template>
            </td>
            <td class="px-3 py-2 text-right text-gray-900">
              {{ formatRupiah(booking.total_price) }}
              <p v-if="booking.discount_amount > 0" class="text-xs text-green-700">
                kupon {{ booking.coupon_code }} (-{{ formatRupiah(booking.discount_amount) }})
              </p>
            </td>
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
