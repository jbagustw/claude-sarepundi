<script setup lang="ts">
import type { Booking } from '~/types/booking'

definePageMeta({ role: 'user' })

const route = useRoute()
const api = useApi()

const booking = ref<Booking | null>(null)
const notFound = ref(false)
const paying = ref(false)
const payError = ref('')

const paymentReturnStatus = typeof route.query.payment === 'string' ? route.query.payment : null

async function loadBooking() {
  try {
    const response = await api<{ data: Booking }>(`/api/bookings/${route.params.id}`)
    booking.value = response.data
  } catch {
    notFound.value = true
  }
}

async function payNow() {
  payError.value = ''
  paying.value = true

  try {
    const response = await api<{ data: { invoice_url: string } }>(`/api/bookings/${route.params.id}/pay`, {
      method: 'POST',
    })
    window.location.href = response.data.invoice_url
  } catch (error: any) {
    payError.value = error?.data?.message || 'Gagal memulai pembayaran. Silakan coba lagi.'
  } finally {
    paying.value = false
  }
}

onMounted(loadBooking)
</script>

<template>
  <div class="mx-auto max-w-xl">
    <p v-if="notFound" class="text-gray-600">Booking tidak ditemukan.</p>

    <div v-else-if="booking">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">{{ booking.booking_code }}</h1>
        <span class="rounded px-3 py-1 text-sm font-medium" :class="BOOKING_STATUS_COLOR[booking.status]">
          {{ BOOKING_STATUS_LABEL[booking.status] }}
        </span>
      </div>

      <p v-if="paymentReturnStatus === 'success' && booking.status === 'pending_payment'" class="mt-4 rounded bg-blue-50 p-3 text-sm text-blue-800">
        Pembayaran sedang diproses. Halaman ini akan memperbarui status begitu konfirmasi diterima dari Xendit.
      </p>
      <p v-else-if="paymentReturnStatus === 'failed'" class="mt-4 rounded bg-red-50 p-3 text-sm text-red-700">
        Pembayaran belum berhasil. Kamu bisa mencoba lagi di bawah.
      </p>

      <div class="mt-6 rounded border border-gray-200 bg-white p-4">
        <div class="flex gap-4">
          <img
            v-if="booking.villa.primary_image"
            :src="booking.villa.primary_image"
            class="h-20 w-20 rounded object-cover"
            alt=""
          >
          <div>
            <NuxtLink :to="`/villas/${booking.villa.slug}`" class="font-semibold text-gray-900 hover:underline">
              {{ booking.villa.name }}
            </NuxtLink>
            <p class="text-sm text-gray-600">{{ booking.villa.city }}</p>
          </div>
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-y-2 text-sm">
          <dt class="text-gray-500">Check-in</dt>
          <dd class="text-gray-900">{{ booking.check_in_date }}</dd>
          <dt class="text-gray-500">Check-out</dt>
          <dd class="text-gray-900">{{ booking.check_out_date }}</dd>
          <dt class="text-gray-500">Jumlah Tamu</dt>
          <dd class="text-gray-900">{{ booking.guest_count }}</dd>
        </dl>

        <div class="mt-4 border-t border-gray-200 pt-4">
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Total Harga</span>
            <span class="font-semibold text-gray-900">{{ formatRupiah(booking.total_price) }}</span>
          </div>
        </div>

        <template v-if="booking.status === 'pending_payment'">
          <button
            class="mt-4 w-full rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700 disabled:opacity-50"
            :disabled="paying"
            @click="payNow"
          >
            {{ paying ? 'Memproses...' : 'Bayar Sekarang' }}
          </button>
          <p v-if="payError" class="mt-2 text-sm text-red-600">{{ payError }}</p>
        </template>

        <p v-if="booking.status === 'menunggu_konfirmasi'" class="mt-4 rounded bg-blue-50 p-3 text-sm text-blue-800">
          Pembayaran berhasil. Booking menunggu konfirmasi dari mitra
          <span v-if="booking.mitra_confirmation_deadline">
            (batas waktu {{ new Date(booking.mitra_confirmation_deadline).toLocaleString('id-ID') }}).
          </span>
        </p>
      </div>
    </div>
  </div>
</template>
