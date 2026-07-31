<script setup lang="ts">
import type { AvailabilityResult } from '~/types/booking'

const props = defineProps<{
  bookableType: 'villa' | 'homestay'
  bookableSlug: string
  bookableId: number
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

const resourcePathSegment = computed(() => (props.bookableType === 'homestay' ? 'homestays' : 'villas'))

async function checkAvailability() {
  errorMessage.value = ''
  result.value = null

  if (!checkInDate.value || !checkOutDate.value) {
    errorMessage.value = 'Pilih tanggal check-in dan check-out.'
    return
  }

  checking.value = true
  try {
    const response = await api<{ data: AvailabilityResult }>(`/api/${resourcePathSegment.value}/${props.bookableSlug}/availability`, {
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
        bookable_type: props.bookableType,
        bookable_id: props.bookableId,
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
  <div class="card sticky top-4 overflow-hidden">
    <div class="bg-brand-terracotta px-4 py-3 text-white">
      <p class="text-xs text-white/80">Mulai dari</p>
      <p class="text-lg font-semibold">{{ formatRupiah(basePrice) }} <span class="text-sm font-normal text-white/80">/ malam</span></p>
    </div>

    <div class="p-4">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-700" for="check-in">Check-in</label>
          <input
            id="check-in"
            v-model="checkInDate"
            type="date"
            :min="today"
            class="field-input mt-1"
          >
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700" for="check-out">Check-out</label>
          <input
            id="check-out"
            v-model="checkOutDate"
            type="date"
            :min="checkInDate || today"
            class="field-input mt-1"
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
          class="field-input mt-1"
        >
      </div>

      <button
        class="btn-outline mt-4 w-full"
        :disabled="checking"
        @click="checkAvailability"
      >
        {{ checking ? 'Mengecek...' : 'Cek Ketersediaan' }}
      </button>

      <p v-if="errorMessage" class="mt-3 text-sm text-red-600">{{ errorMessage }}</p>

      <div v-if="result">
        <div v-if="result.available" class="mt-4 space-y-1 rounded-xl bg-brand-sage/15 p-3 text-sm text-brand-brown-dark">
          <p>Tersedia untuk {{ result.nights }} malam.</p>
          <p class="font-semibold">Total: {{ formatRupiah(result.total_price) }}</p>
        </div>
        <p v-else class="mt-3 text-sm text-red-600">{{ result.reason }}</p>

        <template v-if="result.available">
          <button
            v-if="authStore.role === 'user'"
            class="btn-accent mt-3 w-full"
            :disabled="booking"
            @click="createBooking"
          >
            {{ booking ? 'Memproses...' : 'Pesan Sekarang' }}
          </button>
          <NuxtLink
            v-else-if="!authStore.isAuthenticated"
            :to="`/login?redirect=${encodeURIComponent(`/${resourcePathSegment}/${bookableSlug}`)}`"
            class="btn-primary mt-3 block w-full text-center"
          >
            Masuk untuk Booking
          </NuxtLink>
          <p v-else class="mt-3 text-sm text-gray-500">
            Hanya akun pencari yang bisa melakukan booking.
          </p>
        </template>
      </div>
    </div>
  </div>
</template>
