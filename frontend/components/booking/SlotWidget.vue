<script setup lang="ts">
import type { GatheringVenueAvailabilityResult, GatheringVenueSlotAvailability } from '~/types/booking'

const props = defineProps<{
  venueSlug: string
  venueId: number
  capacity: number
  startingPrice: number | null
}>()

const api = useApi()
const authStore = useAuthStore()
const router = useRouter()

const today = new Date().toISOString().slice(0, 10)

const date = ref('')
const guestCount = ref(Math.min(10, props.capacity))
const selectedSlotId = ref<number | null>(null)

const checking = ref(false)
const booking = ref(false)
const result = ref<GatheringVenueAvailabilityResult | null>(null)
const errorMessage = ref('')

const selectedSlot = computed<GatheringVenueSlotAvailability | undefined>(
  () => result.value?.slots.find(s => s.id === selectedSlotId.value)
)

async function checkAvailability() {
  errorMessage.value = ''
  result.value = null
  selectedSlotId.value = null

  if (!date.value) {
    errorMessage.value = 'Pilih tanggal acara.'
    return
  }

  checking.value = true
  try {
    const response = await api<{ data: GatheringVenueAvailabilityResult }>(`/api/gathering-venues/${props.venueSlug}/availability`, {
      query: { date: date.value },
    })
    result.value = response.data
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Gagal mengecek ketersediaan.'
  } finally {
    checking.value = false
  }
}

async function createBooking() {
  if (!selectedSlotId.value) return

  booking.value = true
  errorMessage.value = ''

  try {
    const response = await api<{ data: { id: number } }>('/api/bookings', {
      method: 'POST',
      body: {
        bookable_type: 'gathering_venue',
        bookable_id: props.venueId,
        gathering_venue_slot_id: selectedSlotId.value,
        check_in_date: date.value,
        guest_count: guestCount.value,
      },
    })
    router.push(`/user/bookings/${response.data.id}`)
  } catch (error: any) {
    errorMessage.value = error?.data?.errors?.check_in_date?.[0] || error?.data?.message || 'Gagal membuat booking.'
  } finally {
    booking.value = false
  }
}
</script>

<template>
  <div class="card sticky top-4 overflow-hidden">
    <div class="bg-brand-terracotta px-4 py-3 text-white">
      <p class="text-xs text-white/80">Mulai dari</p>
      <p class="text-lg font-semibold">
        {{ startingPrice !== null ? formatRupiah(startingPrice) : 'Hubungi mitra' }}
        <span class="text-sm font-normal text-white/80">/ sesi</span>
      </p>
    </div>

    <div class="p-4">
      <label class="block text-xs font-medium text-gray-700" for="event-date">Tanggal Acara</label>
      <input
        id="event-date"
        v-model="date"
        type="date"
        :min="today"
        class="field-input mt-1"
      >

      <div class="mt-3">
        <label class="block text-xs font-medium text-gray-700" for="guest-count">Jumlah Tamu</label>
        <input
          id="guest-count"
          v-model.number="guestCount"
          type="number"
          min="1"
          :max="capacity"
          class="field-input mt-1"
        >
        <p class="mt-1 text-xs text-gray-400">Kapasitas maksimal {{ capacity }} orang</p>
      </div>

      <button
        class="btn-outline mt-4 w-full"
        :disabled="checking"
        @click="checkAvailability"
      >
        {{ checking ? 'Mengecek...' : 'Cek Slot Tersedia' }}
      </button>

      <p v-if="errorMessage" class="mt-3 text-sm text-red-600">{{ errorMessage }}</p>

      <div v-if="result">
        <p v-if="result.slots.length === 0" class="mt-4 text-sm text-gray-500">
          Belum ada slot waktu yang dikonfigurasi untuk lokasi ini.
        </p>

        <ul v-else class="mt-4 space-y-2">
          <li
            v-for="slot in result.slots"
            :key="slot.id"
          >
            <button
              type="button"
              class="flex w-full items-center justify-between rounded-xl border px-3 py-2 text-left text-sm transition"
              :class="[
                !slot.available ? 'cursor-not-allowed border-gray-100 bg-gray-50 opacity-60' :
                selectedSlotId === slot.id ? 'border-brand-terracotta bg-brand-terracotta/10' : 'border-gray-200 hover:border-brand-terracotta',
              ]"
              :disabled="!slot.available"
              @click="selectedSlotId = slot.id"
            >
              <div>
                <p class="font-medium text-gray-900">{{ slot.name }}</p>
                <p class="text-xs text-gray-500">{{ slot.start_time }} - {{ slot.end_time }}</p>
              </div>
              <div class="text-right">
                <p class="font-semibold text-gray-900">{{ formatRupiah(slot.price) }}</p>
                <p v-if="!slot.available" class="text-xs text-red-500">Sudah dipesan</p>
              </div>
            </button>
          </li>
        </ul>

        <template v-if="selectedSlot">
          <button
            v-if="authStore.role === 'user'"
            class="btn-accent mt-4 w-full"
            :disabled="booking"
            @click="createBooking"
          >
            {{ booking ? 'Memproses...' : `Pesan Sekarang — ${formatRupiah(selectedSlot.price)}` }}
          </button>
          <NuxtLink
            v-else-if="!authStore.isAuthenticated"
            :to="`/login?redirect=${encodeURIComponent(`/gathering-venues/${venueSlug}`)}`"
            class="btn-primary mt-4 block w-full text-center"
          >
            Masuk untuk Booking
          </NuxtLink>
          <p v-else class="mt-4 text-sm text-gray-500">
            Hanya akun pencari yang bisa melakukan booking.
          </p>
        </template>
      </div>
    </div>
  </div>
</template>
