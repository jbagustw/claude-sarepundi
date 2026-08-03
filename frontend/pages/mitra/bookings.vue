<script setup lang="ts">
import type { MitraBooking } from '~/types/mitraBooking'

definePageMeta({ role: 'mitra' })

const api = useApi()
const apiBase = useRuntimeConfig().public.apiBase
const bookings = ref<MitraBooking[]>([])
const loading = ref(true)
const statusFilter = ref('')

const statusOptions = [
  { value: '', label: 'Semua Status' },
  { value: 'pending_payment', label: 'Menunggu Pembayaran' },
  { value: 'dikonfirmasi', label: 'Dikonfirmasi' },
  { value: 'dibatalkan_user', label: 'Dibatalkan User' },
  { value: 'checked_in', label: 'Check-in' },
  { value: 'selesai', label: 'Selesai' },
]

async function loadBookings() {
  loading.value = true
  const query: Record<string, string> = {}
  if (statusFilter.value) query.status = statusFilter.value

  const response = await api<{ data: MitraBooking[] }>('/api/mitra/bookings', { query })
  bookings.value = response.data
  loading.value = false
}

onMounted(loadBookings)
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-gray-900">Kelola Booking</h1>
    <p class="mt-1 text-sm text-gray-600">
      Semua booking untuk listing kamu. Booking otomatis dikonfirmasi begitu user selesai bayar — jadwal yang sudah kamu buka di kalender ketersediaan adalah komitmenmu, jadi tidak perlu direspon manual lagi.
    </p>

    <form class="mt-4 flex gap-3" @submit.prevent="loadBookings">
      <select v-model="statusFilter" class="field-input" @change="loadBookings">
        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </form>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="bookings.length === 0" class="mt-6 text-gray-600">Tidak ada booking yang cocok.</p>

    <div v-else class="mt-6 space-y-4">
      <div v-for="booking in bookings" :key="booking.id" class="card p-4">
        <div class="flex items-start justify-between">
          <div>
            <p class="font-semibold text-gray-900">{{ booking.bookable.name }}</p>
            <p class="text-sm text-gray-600">{{ booking.guest.name }} &middot; {{ booking.guest.email }}</p>
            <p class="text-xs text-gray-500">{{ booking.booking_code }}</p>
          </div>
          <div class="text-right">
            <span class="rounded px-2 py-0.5 text-xs font-medium" :class="BOOKING_STATUS_COLOR[booking.status]">
              {{ BOOKING_STATUS_LABEL[booking.status] }}
            </span>
            <p class="mt-1 text-sm font-medium text-gray-900">{{ formatRupiah(booking.mitra_payout_amount) }}</p>
            <p class="text-xs text-gray-500">setelah komisi</p>
          </div>
        </div>

        <dl v-if="booking.bookable.type === 'gathering_venue'" class="mt-3 grid grid-cols-3 gap-y-1 text-sm">
          <dt class="text-gray-500">Tanggal</dt>
          <dd class="col-span-2 text-gray-900">{{ booking.check_in_date }}</dd>
          <dt v-if="booking.slot" class="text-gray-500">Sesi</dt>
          <dd v-if="booking.slot" class="col-span-2 text-gray-900">{{ booking.slot.name }} ({{ booking.slot.start_time }}-{{ booking.slot.end_time }})</dd>
          <dt class="text-gray-500">Tamu</dt>
          <dd class="col-span-2 text-gray-900">{{ booking.guest_count }}</dd>
        </dl>
        <dl v-else class="mt-3 grid grid-cols-3 gap-y-1 text-sm">
          <dt class="text-gray-500">Check-in</dt>
          <dd class="col-span-2 text-gray-900">{{ booking.check_in_date }}</dd>
          <dt class="text-gray-500">Check-out</dt>
          <dd class="col-span-2 text-gray-900">{{ booking.check_out_date }}</dd>
          <dt class="text-gray-500">Tamu</dt>
          <dd class="col-span-2 text-gray-900">{{ booking.guest_count }}</dd>
          <template v-if="booking.bookable.type === 'transport'">
            <dt class="text-gray-500">Opsi</dt>
            <dd class="col-span-2 text-gray-900">{{ booking.transport_with_driver ? 'Dengan Sopir' : 'Lepas Kunci' }}</dd>
          </template>
        </dl>

        <div v-if="booking.status !== 'pending_payment'" class="mt-3 flex gap-3 border-t border-gray-100 pt-3 text-sm">
          <a :href="`${apiBase}/api/bookings/${booking.id}/voucher`" class="font-medium text-brand-brown underline">
            Unduh Voucher (PDF)
          </a>
          <a :href="`${apiBase}/api/bookings/${booking.id}/receipt`" class="font-medium text-brand-brown underline">
            Unduh Receipt (PDF)
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
