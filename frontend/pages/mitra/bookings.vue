<script setup lang="ts">
import type { MitraBooking } from '~/types/mitraBooking'

definePageMeta({ role: 'mitra' })

const api = useApi()
const bookings = ref<MitraBooking[]>([])
const loading = ref(true)
const actingOn = ref<number | null>(null)

async function loadBookings() {
  loading.value = true
  const response = await api<{ data: MitraBooking[] }>('/api/mitra/bookings', {
    query: { status: 'menunggu_konfirmasi' },
  })
  bookings.value = response.data
  loading.value = false
}

async function accept(booking: MitraBooking) {
  actingOn.value = booking.id
  try {
    await api(`/api/mitra/bookings/${booking.id}/accept`, { method: 'POST' })
    await loadBookings()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal menerima booking.')
  } finally {
    actingOn.value = null
  }
}

async function reject(booking: MitraBooking) {
  if (!confirm(`Tolak booking ${booking.booking_code}? User akan menerima refund 100%.`)) return

  actingOn.value = booking.id
  try {
    await api(`/api/mitra/bookings/${booking.id}/reject`, { method: 'POST' })
    await loadBookings()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal menolak booking.')
  } finally {
    actingOn.value = null
  }
}

onMounted(loadBookings)
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900">Konfirmasi Booking</h1>
    <p class="mt-1 text-sm text-gray-600">
      Booking yang sudah dibayar dan menunggu konfirmasi kamu. Wajib direspon dalam 24 jam,
      jika tidak booking otomatis dibatalkan dan user direfund 100%.
    </p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="bookings.length === 0" class="mt-6 text-gray-600">Tidak ada booking yang menunggu konfirmasi.</p>

    <div v-else class="mt-6 space-y-4">
      <div v-for="booking in bookings" :key="booking.id" class="rounded border border-gray-200 bg-white p-4">
        <div class="flex items-start justify-between">
          <div>
            <p class="font-semibold text-gray-900">{{ booking.villa.name }}</p>
            <p class="text-sm text-gray-600">{{ booking.guest.name }} &middot; {{ booking.guest.email }}</p>
            <p class="text-xs text-gray-500">{{ booking.booking_code }}</p>
          </div>
          <div class="text-right text-sm">
            <p class="font-medium text-gray-900">{{ formatRupiah(booking.mitra_payout_amount) }}</p>
            <p class="text-gray-500">setelah komisi</p>
          </div>
        </div>

        <dl class="mt-3 grid grid-cols-3 gap-y-1 text-sm">
          <dt class="text-gray-500">Check-in</dt>
          <dd class="col-span-2 text-gray-900">{{ booking.check_in_date }}</dd>
          <dt class="text-gray-500">Check-out</dt>
          <dd class="col-span-2 text-gray-900">{{ booking.check_out_date }}</dd>
          <dt class="text-gray-500">Tamu</dt>
          <dd class="col-span-2 text-gray-900">{{ booking.guest_count }}</dd>
        </dl>

        <p v-if="booking.mitra_confirmation_deadline" class="mt-2 text-xs text-yellow-700">
          Batas konfirmasi: {{ new Date(booking.mitra_confirmation_deadline).toLocaleString('id-ID') }}
        </p>

        <div class="mt-3 flex gap-2">
          <button
            class="rounded bg-green-700 px-3 py-1.5 text-sm text-white hover:bg-green-800 disabled:opacity-50"
            :disabled="actingOn === booking.id"
            @click="accept(booking)"
          >
            Terima
          </button>
          <button
            class="rounded bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700 disabled:opacity-50"
            :disabled="actingOn === booking.id"
            @click="reject(booking)"
          >
            Tolak
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
