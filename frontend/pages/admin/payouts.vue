<script setup lang="ts">
import type { Payout } from '~/types/payout'

definePageMeta({ role: 'admin' })

const api = useApi()
const payouts = ref<Payout[]>([])
const loading = ref(true)
const running = ref(false)
const actingOn = ref<number | null>(null)
const statusFilter = ref('')

const statusOptions = [
  { value: '', label: 'Semua Status' },
  { value: 'pending', label: 'Diproses' },
  { value: 'completed', label: 'Selesai' },
  { value: 'failed', label: 'Gagal' },
]

async function loadPayouts() {
  loading.value = true
  const query: Record<string, string> = {}
  if (statusFilter.value) query.status = statusFilter.value

  const response = await api<{ data: Payout[] }>('/api/admin/payouts', { query })
  payouts.value = response.data
  loading.value = false
}

async function runPayouts() {
  running.value = true
  try {
    const response = await api<{ data: Payout[] }>('/api/admin/payouts/run', { method: 'POST' })
    alert(`${response.data.length} payout baru dibuat.`)
    await loadPayouts()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal menjalankan payout.')
  } finally {
    running.value = false
  }
}

async function retry(payout: Payout) {
  actingOn.value = payout.id
  try {
    await api(`/api/admin/payouts/${payout.id}/retry`, { method: 'POST' })
    await loadPayouts()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mencoba ulang payout.')
  } finally {
    actingOn.value = null
  }
}

onMounted(loadPayouts)
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="font-display text-2xl font-bold text-gray-900">Payout Mitra</h1>
      <button
        class="btn-primary"
        :disabled="running"
        @click="runPayouts"
      >
        {{ running ? 'Memproses...' : 'Jalankan Payout Sekarang' }}
      </button>
    </div>
    <p class="mt-1 text-sm text-gray-600">
      Payout otomatis mengumpulkan semua booking selesai yang belum dibayarkan, terjadwal
      tiap tanggal 1 & 15. Gunakan tombol di atas untuk menjalankan lebih awal.
    </p>

    <form class="mt-4 flex gap-3" @submit.prevent="loadPayouts">
      <select v-model="statusFilter" class="field-input" @change="loadPayouts">
        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </form>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="payouts.length === 0" class="mt-6 text-gray-600">Belum ada payout.</p>

    <div v-else class="mt-6 space-y-3">
      <div v-for="payout in payouts" :key="payout.id" class="card p-4">
        <div class="flex items-start justify-between">
          <div>
            <p class="font-semibold text-gray-900">{{ payout.mitra?.business_name }}</p>
            <p class="text-sm text-gray-600">
              {{ payout.period_start }} &rarr; {{ payout.period_end }} &middot; {{ payout.booking_count }} booking
            </p>
          </div>
          <div class="text-right">
            <span class="rounded px-2 py-0.5 text-xs font-medium" :class="PAYOUT_STATUS_COLOR[payout.status]">
              {{ PAYOUT_STATUS_LABEL[payout.status] }}
            </span>
            <p class="mt-1 text-sm font-medium text-gray-900">{{ formatRupiah(payout.amount) }}</p>
          </div>
        </div>

        <p v-if="payout.status === 'failed' && payout.failure_reason" class="mt-2 text-sm text-red-600">
          {{ payout.failure_reason }}
        </p>

        <button
          v-if="payout.status === 'failed'"
          class="btn-primary mt-3 !px-3 !py-1.5"
          :disabled="actingOn === payout.id"
          @click="retry(payout)"
        >
          Coba Lagi
        </button>
      </div>
    </div>
  </div>
</template>
