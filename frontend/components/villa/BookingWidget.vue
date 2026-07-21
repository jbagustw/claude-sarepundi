<script setup lang="ts">
import type { AvailabilityResult } from '~/types/booking'

const props = defineProps<{
  villaSlug: string
  villaId: number
  basePrice: number
}>()

const api = useApi()
const authStore = useAuthStore()
const router = useRouter()

const today = new Date().toISOString().slice(0, 10)

const checkInDate = ref('')
const checkOutDate = ref('')
const guestCount = ref(2)

const checking = ref(false)
const booking = ref(false)
const result = ref<AvailabilityResult | null>(null)
const errorMessage = ref('')

async function checkAvailability() {
  errorMessage.value = ''
  result.value = null

  if (!checkInDate.value || !checkOutDate.value) {
    errorMessage.value = 'Pilih tanggal check-in dan check-out.'
    return
  }

  checking.value = true
  try {
    const response = await api<{ data: AvailabilityResult }>(`/api/villas/${props.villaSlug}/availability`, {
      query: {
        check_in_date: checkInDate.value,
        check_out_date: checkOutDate.value,
        guest_count: guestCount.value,
      },
    })
    result.value = response.data
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Gagal mengecek ketersediaan.'
  } finally {
    checking.value = false
  }
}

async function createBooking() {
  booking.value = true
  errorMessage.value = ''

  try {
    const response = await api<{ data: { id: number } }>('/api/bookings', {
      method: 'POST',
      body: {
        villa_id: props.villaId,
        check_in_date: checkInDate.value,
        check_out_date: checkOutDate.value,
        guest_count: guestCount.value,
      },
    })
    router.push(`/user/bookings/${response.data.id}`)
  } catch (error: any) {
    errorMessage.value = error?.data?.errors?.check_in_date?.[0] || error?.data?.message || 'Gagal membuat booking.'
    result.value = null
  } finally {
    booking.value = false
  }
}
</script>

<template>
  <div class="rounded border border-gray-200 bg-white p-4">
    <p class="text-lg font-semibold text-gray-900">{{ formatRupiah(basePrice) }} <span class="text-sm font-normal text-gray-500">/ malam</span></p>

    <div class="mt-4 grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-medium text-gray-700" for="check-in">Check-in</label>
        <input
          id="check-in"
          v-model="checkInDate"
          type="date"
          :min="today"
          class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-gray-900 focus:outline-none"
        >
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-700" for="check-out">Check-out</label>
        <input
          id="check-out"
          v-model="checkOutDate"
          type="date"
          :min="checkInDate || today"
          class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-gray-900 focus:outline-none"
        >
      </div>
    </div>

    <div class="mt-3">
      <label class="block text-xs font-medium text-gray-700" for="guest-count">Jumlah Tamu</label>
      <input
        id="guest-count"
        v-model.number="guestCount"
        type="number"
        min="1"
        class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-gray-900 focus:outline-none"
      >
    </div>

    <button
      class="mt-4 w-full rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700 disabled:opacity-50"
      :disabled="checking"
      @click="checkAvailability"
    >
      {{ checking ? 'Mengecek...' : 'Cek Ketersediaan' }}
    </button>

    <p v-if="errorMessage" class="mt-3 text-sm text-red-600">{{ errorMessage }}</p>

    <div v-if="result">
      <div v-if="result.available" class="mt-4 space-y-1 rounded bg-green-50 p-3 text-sm text-green-800">
        <p>Tersedia untuk {{ result.nights }} malam.</p>
        <p class="font-semibold">Total: {{ formatRupiah(result.total_price) }}</p>
      </div>
      <p v-else class="mt-3 text-sm text-red-600">{{ result.reason }}</p>

      <template v-if="result.available">
        <button
          v-if="authStore.role === 'user'"
          class="mt-3 w-full rounded bg-green-700 px-4 py-2 text-sm text-white hover:bg-green-800 disabled:opacity-50"
          :disabled="booking"
          @click="createBooking"
        >
          {{ booking ? 'Memproses...' : 'Booking Sekarang' }}
        </button>
        <NuxtLink
          v-else-if="!authStore.isAuthenticated"
          :to="`/login?redirect=${encodeURIComponent(`/villas/${villaSlug}`)}`"
          class="mt-3 block w-full rounded bg-gray-900 px-4 py-2 text-center text-sm text-white hover:bg-gray-700"
        >
          Masuk untuk Booking
        </NuxtLink>
        <p v-else class="mt-3 text-sm text-gray-500">
          Hanya akun pencari villa yang bisa melakukan booking.
        </p>
      </template>
    </div>
  </div>
</template>
