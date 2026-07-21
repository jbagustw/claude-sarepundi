<script setup lang="ts">
import type { Payout } from '~/types/payout'

definePageMeta({ role: 'mitra' })

const api = useApi()
const payouts = ref<Payout[]>([])
const loading = ref(true)

async function loadPayouts() {
  loading.value = true
  const response = await api<{ data: Payout[] }>('/api/mitra/payouts')
  payouts.value = response.data
  loading.value = false
}

onMounted(loadPayouts)
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900">Laporan Payout</h1>
    <p class="mt-1 text-sm text-gray-600">
      Pencairan dana dari platform, dikumpulkan dari booking yang sudah selesai.
    </p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="payouts.length === 0" class="mt-6 text-gray-600">
      Belum ada payout. Payout akan dibuat otomatis setelah booking selesai.
    </p>

    <div v-else class="mt-6 overflow-x-auto rounded border border-gray-200 bg-white">
      <table class="min-w-full text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-3 py-2">Periode</th>
            <th class="px-3 py-2 text-right">Jumlah Booking</th>
            <th class="px-3 py-2 text-right">Total</th>
            <th class="px-3 py-2">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="payout in payouts" :key="payout.id">
            <td class="px-3 py-2 text-gray-900">{{ payout.period_start }} &rarr; {{ payout.period_end }}</td>
            <td class="px-3 py-2 text-right text-gray-600">{{ payout.booking_count }}</td>
            <td class="px-3 py-2 text-right font-medium text-gray-900">{{ formatRupiah(payout.amount) }}</td>
            <td class="px-3 py-2">
              <span class="rounded px-2 py-0.5 text-xs font-medium" :class="PAYOUT_STATUS_COLOR[payout.status]">
                {{ PAYOUT_STATUS_LABEL[payout.status] }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
