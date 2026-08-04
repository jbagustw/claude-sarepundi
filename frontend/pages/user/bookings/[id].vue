<script setup lang="ts">
import type { Booking } from '~/types/booking'
import type { Review } from '~/types/review'

definePageMeta({ role: 'user' })

const route = useRoute()
const api = useApi()
const apiBase = useRuntimeConfig().public.apiBase

const booking = ref<Booking | null>(null)
const notFound = ref(false)
const paying = ref(false)
const payError = ref('')
const cancelling = ref(false)
const cancelError = ref('')

const paymentReturnStatus = typeof route.query.payment === 'string' ? route.query.payment : null

const cancellationReasonLabel: Record<string, string> = {
  user_cancel_confirmed: 'Dibatalkan olehmu',
}

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

const canCancel = computed(() => booking.value?.status === 'dikonfirmasi')

const refundPreview = computed(() => {
  if (!booking.value) return ''

  const daysUntilCheckIn = Math.floor(
    (new Date(booking.value.check_in_date).getTime() - new Date().setHours(0, 0, 0, 0)) / 86400000
  )
  return daysUntilCheckIn >= 2
    ? 'kamu akan menerima refund 85%'
    : 'karena check-in kurang dari 2 hari lagi, tidak ada refund (0%)'
})

async function cancelBooking() {
  if (!confirm(`Batalkan booking ini? Berdasarkan kebijakan saat ini, ${refundPreview.value}.`)) return

  cancelError.value = ''
  cancelling.value = true

  try {
    const response = await api<{ data: Booking }>(`/api/bookings/${route.params.id}/cancel`, { method: 'POST' })
    booking.value = response.data
  } catch (error: any) {
    cancelError.value = error?.data?.message || 'Gagal membatalkan booking.'
  } finally {
    cancelling.value = false
  }
}

function onReviewSubmitted(review: Review) {
  if (booking.value) booking.value.review = review
}

const bookablePath = computed(() => {
  if (!booking.value) return ''
  const segments: Record<string, string> = {
    homestay: 'homestays', glamping: 'glampings', apartment: 'apartments', gathering_venue: 'gathering-venues', transport: 'transports', villa: 'villas',
  }
  return `/${segments[booking.value.bookable.type]}/${booking.value.bookable.slug}`
})

onMounted(loadBooking)
</script>

<template>
  <div class="mx-auto max-w-xl">
    <p v-if="notFound" class="text-gray-600">Booking tidak ditemukan.</p>

    <div v-else-if="booking">
      <div class="flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-gray-900">{{ booking.booking_code }}</h1>
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

      <div class="card mt-6 p-4">
        <div class="flex gap-4">
          <img
            v-if="booking.bookable.primary_image"
            :src="booking.bookable.primary_image"
            class="h-20 w-20 rounded object-cover"
            alt=""
          >
          <div>
            <NuxtLink :to="bookablePath" class="font-semibold text-gray-900 hover:underline">
              {{ booking.bookable.name }}
            </NuxtLink>
            <p class="text-sm text-gray-600">{{ booking.bookable.city }}</p>
          </div>
        </div>

        <dl v-if="booking.bookable.type === 'gathering_venue'" class="mt-4 grid grid-cols-2 gap-y-2 text-sm">
          <dt class="text-gray-500">Tanggal Acara</dt>
          <dd class="text-gray-900">{{ booking.check_in_date }}</dd>
          <dt v-if="booking.slot" class="text-gray-500">Sesi</dt>
          <dd v-if="booking.slot" class="text-gray-900">{{ booking.slot.name }} ({{ booking.slot.start_time }} - {{ booking.slot.end_time }})</dd>
          <dt class="text-gray-500">Jumlah Tamu</dt>
          <dd class="text-gray-900">{{ booking.guest_count }}</dd>
        </dl>
        <dl v-else class="mt-4 grid grid-cols-2 gap-y-2 text-sm">
          <dt class="text-gray-500">Check-in</dt>
          <dd class="text-gray-900">{{ booking.check_in_date }}</dd>
          <dt class="text-gray-500">Check-out</dt>
          <dd class="text-gray-900">{{ booking.check_out_date }}</dd>
          <dt class="text-gray-500">Jumlah Tamu</dt>
          <dd class="text-gray-900">{{ booking.guest_count }}</dd>
          <template v-if="booking.bookable.type === 'transport'">
            <dt class="text-gray-500">Opsi</dt>
            <dd class="text-gray-900">{{ booking.transport_with_driver ? 'Dengan Sopir' : 'Lepas Kunci' }}</dd>
          </template>
        </dl>

        <div class="mt-4 border-t border-gray-200 pt-4">
          <template v-if="booking.discount_amount > 0">
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Subtotal</span>
              <span class="text-gray-900">{{ formatRupiah(booking.subtotal) }}</span>
            </div>
            <div class="mt-1 flex justify-between text-sm">
              <span class="text-gray-600">Diskon Kupon<span v-if="booking.coupon_code"> ({{ booking.coupon_code }})</span></span>
              <span class="text-green-700">-{{ formatRupiah(booking.discount_amount) }}</span>
            </div>
            <div class="mt-2 flex justify-between border-t border-gray-100 pt-2 text-sm">
              <span class="text-gray-600">Total Harga</span>
              <span class="font-semibold text-gray-900">{{ formatRupiah(booking.total_price) }}</span>
            </div>
          </template>
          <div v-else class="flex justify-between text-sm">
            <span class="text-gray-600">Total Harga</span>
            <span class="font-semibold text-gray-900">{{ formatRupiah(booking.total_price) }}</span>
          </div>
        </div>

        <template v-if="booking.status === 'pending_payment'">
          <button
            class="btn-primary mt-4 w-full"
            :disabled="paying"
            @click="payNow"
          >
            {{ paying ? 'Memproses...' : 'Bayar Sekarang' }}
          </button>
          <p v-if="payError" class="mt-2 text-sm text-red-600">{{ payError }}</p>
        </template>

        <div v-else class="mt-4 flex gap-3 border-t border-gray-200 pt-4 text-sm">
          <a :href="`${apiBase}/api/bookings/${booking.id}/voucher`" class="font-medium text-brand-brown underline">
            Unduh Voucher (PDF)
          </a>
          <a :href="`${apiBase}/api/bookings/${booking.id}/receipt`" class="font-medium text-brand-brown underline">
            Unduh Receipt (PDF)
          </a>
        </div>

        <p v-if="booking.status === 'dikonfirmasi'" class="mt-4 rounded bg-green-50 p-3 text-sm text-green-800">
          Pembayaran berhasil, booking kamu sudah dikonfirmasi. Sampai jumpa di tanggal check-in!
        </p>

        <div v-if="booking.status === 'dibatalkan_user'" class="mt-4 rounded bg-red-50 p-3 text-sm text-red-700">
          <p>{{ cancellationReasonLabel[booking.cancellation_reason ?? ''] ?? 'Booking dibatalkan.' }}.</p>
          <p v-if="booking.refund_amount" class="mt-1 font-medium">
            Refund {{ booking.refund_percentage }}% ({{ formatRupiah(booking.refund_amount) }}) sedang diproses ke metode pembayaranmu.
          </p>
          <p v-else-if="booking.refund_percentage === 0" class="mt-1 font-medium">
            Tidak ada refund untuk pembatalan ini (kurang dari H-2 sebelum check-in).
          </p>
        </div>

        <template v-if="canCancel">
          <button
            class="btn-danger-outline mt-4 w-full"
            :disabled="cancelling"
            @click="cancelBooking"
          >
            {{ cancelling ? 'Memproses...' : 'Batalkan Booking' }}
          </button>
          <p v-if="cancelError" class="mt-2 text-sm text-red-600">{{ cancelError }}</p>
        </template>
      </div>

      <div v-if="booking.status === 'selesai'" class="mt-6">
        <ReviewForm v-if="!booking.review" :booking-id="booking.id" @submitted="onReviewSubmitted" />

        <div v-else class="card p-4">
          <p class="font-medium text-gray-900">Ulasanmu</p>
          <div class="mt-2">
            <ReviewStars :rating="booking.review.rating" />
          </div>
          <p v-if="booking.review.comment" class="mt-2 text-sm text-gray-700">{{ booking.review.comment }}</p>

          <div v-if="booking.review.mitra_reply" class="mt-3 rounded-xl bg-brand-sage/10 p-3 text-sm">
            <p class="font-medium text-gray-900">Balasan dari mitra</p>
            <p class="mt-1 text-gray-700">{{ booking.review.mitra_reply }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
