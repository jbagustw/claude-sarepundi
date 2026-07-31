<script setup lang="ts">
import type { AvailabilityResult } from '~/types/booking'

const props = defineProps<{
  bookableType: 'villa' | 'homestay' | 'transport'
  bookableSlug: string
  bookableId: number
  basePrice: number
  transportPricing?: { selfDrive: number | null; withDriver: number | null }
}>()

const api = useApi()
const authStore = useAuthStore()
const router = useRouter()

const today = new Date().toISOString().slice(0, 10)

const checkInDate = ref('')
const checkOutDate = ref('')
const guestCount = ref(2)
const withDriver = ref(props.transportPricing?.selfDrive === null)

const checking = ref(false)
const booking = ref(false)
const result = ref<AvailabilityResult | null>(null)
const errorMessage = ref('')

const resourcePathSegment = computed(() => {
  if (props.bookableType === 'homestay') return 'homestays'
  if (props.bookableType === 'transport') return 'transports'
  return 'villas'
})

async function checkAvailability() {
  errorMessage.value = ''
  result.value = null

  if (!checkInDate.value || !checkOutDate.value) {
    errorMessage.value = 'Pilih tanggal check-in dan check-out.'
    return
  }

  checking.value = true
  try {
    const query: Record<string, string | number> = {
      check_in_date: checkInDate.value,
      check_out_date: checkOutDate.value,
      guest_count: guestCount.value,
    }
    if (props.bookableType === 'transport') query.with_driver = withDriver.value ? 1 : 0

    const response = await api<{ data: AvailabilityResult }>(`/api/${resourcePathSegment.value}/${props.bookableSlug}/availability`, { query })
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
    const body: Record<string, unknown> = {
      bookable_type: props.bookableType,
      bookable_id: props.bookableId,
      check_in_date: checkInDate.value,
      check_out_date: checkOutDate.value,
      guest_count: guestCount.value,
    }
    if (props.bookableType === 'transport') body.with_driver = withDriver.value

    const response = await api<{ data: { id: number } }>('/api/bookings', { method: 'POST', body })
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
      <p class="text-lg font-semibold">{{ formatRupiah(basePrice) }} <span class="text-sm font-normal text-white/80">/ {{ bookableType === 'transport' ? 'hari' : 'malam' }}</span></p>
    </div>

    <div class="p-4">
      <div v-if="bookableType === 'transport' && transportPricing" class="mb-3 grid grid-cols-2 gap-2">
        <button
          type="button"
          class="rounded-xl border px-3 py-2 text-left text-sm transition"
          :class="!withDriver ? 'border-brand-terracotta bg-brand-terracotta/10' : 'border-gray-200'"
          :disabled="transportPricing.selfDrive === null"
          @click="withDriver = false"
        >
          <p class="font-medium text-gray-900">Lepas Kunci</p>
          <p class="text-xs text-gray-500">{{ transportPricing.selfDrive !== null ? formatRupiah(transportPricing.selfDrive) : 'Tidak tersedia' }}</p>
        </button>
        <button
          type="button"
          class="rounded-xl border px-3 py-2 text-left text-sm transition"
          :class="withDriver ? 'border-brand-terracotta bg-brand-terracotta/10' : 'border-gray-200'"
          :disabled="transportPricing.withDriver === null"
          @click="withDriver = true"
        >
          <p class="font-medium text-gray-900">Dengan Sopir</p>
          <p class="text-xs text-gray-500">{{ transportPricing.withDriver !== null ? formatRupiah(transportPricing.withDriver) : 'Tidak tersedia' }}</p>
        </button>
      </div>

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
          <p>Tersedia untuk {{ result.nights }} {{ bookableType === 'transport' ? 'hari' : 'malam' }}.</p>
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
