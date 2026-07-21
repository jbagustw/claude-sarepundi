<script setup lang="ts">
import type { VillaAvailabilityOverride } from '~/types/booking'

const props = defineProps<{
  villaId: number
}>()

const api = useApi()

const overrides = ref<VillaAvailabilityOverride[]>([])
const loading = ref(true)
const saving = ref(false)

const rangeFrom = ref('')
const rangeTo = ref('')
const isAvailable = ref(false)
const customPrice = ref<number | null>(null)
const minStay = ref<number | null>(null)

const today = new Date().toISOString().slice(0, 10)
const horizon = new Date(Date.now() + 90 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10)

async function loadOverrides() {
  loading.value = true
  const response = await api<{ data: VillaAvailabilityOverride[] }>(`/api/mitra/villas/${props.villaId}/availability`, {
    query: { from: today, to: horizon },
  })
  overrides.value = response.data
  loading.value = false
}

function datesInRange(from: string, to: string): string[] {
  const dates: string[] = []
  let cursor = new Date(from)
  const end = new Date(to)
  while (cursor <= end) {
    dates.push(cursor.toISOString().slice(0, 10))
    cursor = new Date(cursor.getTime() + 24 * 60 * 60 * 1000)
  }
  return dates
}

async function saveRange() {
  if (!rangeFrom.value || !rangeTo.value) return

  saving.value = true
  try {
    await api(`/api/mitra/villas/${props.villaId}/availability`, {
      method: 'PUT',
      body: {
        dates: datesInRange(rangeFrom.value, rangeTo.value),
        is_available: isAvailable.value,
        custom_price: customPrice.value || null,
        min_stay: minStay.value || null,
      },
    })
    await loadOverrides()
  } catch {
    alert('Gagal menyimpan ketersediaan.')
  } finally {
    saving.value = false
  }
}

onMounted(loadOverrides)
</script>

<template>
  <div>
    <h2 class="text-lg font-semibold text-gray-900">Kalender Ketersediaan</h2>
    <p class="mt-1 text-sm text-gray-600">Blokir tanggal yang tidak tersedia, atau atur harga/minimum menginap khusus.</p>

    <div class="mt-3 grid grid-cols-2 gap-3 rounded border border-gray-200 bg-white p-4 sm:grid-cols-4">
      <div>
        <label class="block text-xs font-medium text-gray-700" for="avail-from">Dari</label>
        <input id="avail-from" v-model="rangeFrom" type="date" :min="today" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-700" for="avail-to">Sampai</label>
        <input id="avail-to" v-model="rangeTo" type="date" :min="rangeFrom || today" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-700" for="avail-price">Harga Khusus (opsional)</label>
        <input id="avail-price" v-model.number="customPrice" type="number" min="0" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-700" for="avail-min-stay">Min. Menginap (opsional)</label>
        <input id="avail-min-stay" v-model.number="minStay" type="number" min="1" class="mt-1 w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
      </div>

      <div class="col-span-2 flex items-center gap-4 sm:col-span-4">
        <label class="flex items-center gap-1.5 text-sm text-gray-700">
          <input v-model="isAvailable" type="radio" :value="true"> Tersedia
        </label>
        <label class="flex items-center gap-1.5 text-sm text-gray-700">
          <input v-model="isAvailable" type="radio" :value="false"> Blokir
        </label>
        <button
          class="ml-auto rounded bg-gray-900 px-3 py-1.5 text-sm text-white hover:bg-gray-700 disabled:opacity-50"
          :disabled="saving"
          @click="saveRange"
        >
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </div>
    </div>

    <div class="mt-4">
      <p v-if="loading" class="text-sm text-gray-600">Memuat...</p>
      <p v-else-if="overrides.length === 0" class="text-sm text-gray-500">
        Belum ada tanggal khusus dalam 90 hari ke depan. Semua tanggal tersedia dengan harga dasar.
      </p>
      <ul v-else class="divide-y divide-gray-200 rounded border border-gray-200 bg-white text-sm">
        <li v-for="override in overrides" :key="override.id" class="flex items-center justify-between px-3 py-2">
          <span>{{ override.date }}</span>
          <span class="flex items-center gap-3 text-xs">
            <span :class="override.is_available ? 'text-green-700' : 'text-red-600'">
              {{ override.is_available ? 'Tersedia' : 'Blokir' }}
            </span>
            <span v-if="override.custom_price" class="text-gray-600">{{ formatRupiah(override.custom_price) }}</span>
            <span v-if="override.min_stay" class="text-gray-600">Min {{ override.min_stay }} malam</span>
          </span>
        </li>
      </ul>
    </div>
  </div>
</template>
