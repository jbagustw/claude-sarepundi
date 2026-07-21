<script setup lang="ts">
import type { Booking } from '~/types/booking'

definePageMeta({ role: 'user' })

const route = useRoute()
const api = useApi()

const booking = ref<Booking | null>(null)
const notFound = ref(false)

onMounted(async () => {
  try {
    const response = await api<{ data: Booking }>(`/api/bookings/${route.params.id}`)
    booking.value = response.data
  } catch {
    notFound.value = true
  }
})
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

        <p v-if="booking.status === 'pending_payment'" class="mt-4 rounded bg-yellow-50 p-3 text-sm text-yellow-800">
          Booking ini menunggu pembayaran. Integrasi pembayaran akan tersedia setelah modul Xendit selesai.
        </p>
      </div>
    </div>
  </div>
</template>
