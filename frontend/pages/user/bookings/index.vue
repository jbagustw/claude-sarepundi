<script setup lang="ts">
import type { Booking } from '~/types/booking'

definePageMeta({ role: 'user' })

const api = useApi()
const bookings = ref<Booking[]>([])
const loading = ref(true)

async function loadBookings() {
  loading.value = true
  const response = await api<{ data: Booking[] }>('/api/bookings')
  bookings.value = response.data
  loading.value = false
}

onMounted(loadBookings)
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-gray-900">Riwayat Booking</h1>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="bookings.length === 0" class="mt-6 text-gray-600">
      Belum ada booking.
      <NuxtLink to="/villas" class="text-brand-brown underline">Cari villa</NuxtLink>
    </p>

    <div v-else class="mt-6 space-y-3">
      <NuxtLink
        v-for="booking in bookings"
        :key="booking.id"
        :to="`/user/bookings/${booking.id}`"
        class="card flex items-center justify-between p-4 transition hover:shadow-md"
      >
        <div>
          <p class="font-semibold text-gray-900">{{ booking.bookable.name }}</p>
          <p v-if="booking.bookable.type === 'gathering_venue'" class="text-sm text-gray-600">
            {{ booking.check_in_date }}<span v-if="booking.slot"> &middot; {{ booking.slot.name }} ({{ booking.slot.start_time }}-{{ booking.slot.end_time }})</span> &middot; {{ booking.guest_count }} tamu
          </p>
          <p v-else class="text-sm text-gray-600">
            {{ booking.check_in_date }} &rarr; {{ booking.check_out_date }} &middot; {{ booking.guest_count }} tamu
            <span v-if="booking.bookable.type === 'transport'">&middot; {{ booking.transport_with_driver ? 'Dengan Sopir' : 'Lepas Kunci' }}</span>
          </p>
          <p class="text-xs text-gray-500">{{ booking.booking_code }}</p>
        </div>
        <div class="text-right">
          <span class="rounded px-2 py-0.5 text-xs font-medium" :class="BOOKING_STATUS_COLOR[booking.status]">
            {{ BOOKING_STATUS_LABEL[booking.status] }}
          </span>
          <p class="mt-1 text-sm font-medium text-gray-900">{{ formatRupiah(booking.total_price) }}</p>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
