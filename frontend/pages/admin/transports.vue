<script setup lang="ts">
import type { Transport } from '~/types/transport'

definePageMeta({ role: 'admin' })

const api = useApi()
const transports = ref<Transport[]>([])
const loading = ref(true)
const rejectingId = ref<number | null>(null)
const rejectReason = ref('')

async function loadPending() {
  loading.value = true
  const response = await api<{ data: Transport[] }>('/api/admin/transports', {
    query: { status: 'pending_review' },
  })
  transports.value = response.data
  loading.value = false
}

async function approve(transport: Transport) {
  await api(`/api/admin/transports/${transport.id}/approve`, { method: 'POST' })
  await loadPending()
}

function openReject(transport: Transport) {
  rejectingId.value = transport.id
  rejectReason.value = ''
}

async function confirmReject(transport: Transport) {
  if (!rejectReason.value.trim()) return

  await api(`/api/admin/transports/${transport.id}/reject`, {
    method: 'POST',
    body: { reason: rejectReason.value },
  })
  rejectingId.value = null
  await loadPending()
}

onMounted(loadPending)
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-gray-900">Moderasi Transport</h1>
    <p class="mt-1 text-sm text-gray-600">Transport yang menunggu review sebelum dipublikasikan.</p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="transports.length === 0" class="mt-6 text-gray-600">Tidak ada transport yang menunggu review.</p>

    <div v-else class="mt-6 space-y-4">
      <div v-for="transport in transports" :key="transport.id" class="card p-4">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="font-display font-semibold text-gray-900">{{ transport.name }}</h2>
            <p class="text-sm text-gray-600">{{ transport.vehicle_type }} &middot; {{ transport.city }} &middot; {{ transport.mitra.business_name }}</p>
            <p class="text-sm font-medium text-gray-900">
              <span v-if="transport.price_per_day_self_drive">Lepas kunci {{ formatRupiah(transport.price_per_day_self_drive) }}/hari</span>
              <span v-if="transport.price_per_day_self_drive && transport.price_per_day_with_driver"> &middot; </span>
              <span v-if="transport.price_per_day_with_driver">Dengan supir {{ formatRupiah(transport.price_per_day_with_driver) }}/hari</span>
            </p>
          </div>
          <div class="flex gap-2 text-sm">
            <button
              class="rounded-full bg-green-700 px-3 py-1.5 text-white hover:bg-green-800"
              @click="approve(transport)"
            >
              Setujui
            </button>
            <button
              class="rounded-full bg-red-600 px-3 py-1.5 text-white hover:bg-red-700"
              @click="openReject(transport)"
            >
              Tolak
            </button>
          </div>
        </div>

        <p v-if="transport.description" class="mt-2 text-sm text-gray-600">{{ transport.description }}</p>

        <div class="mt-2 flex flex-wrap gap-2">
          <img
            v-for="image in transport.images"
            :key="image.id"
            :src="image.url"
            class="h-16 w-16 rounded object-cover"
            alt=""
          >
        </div>

        <div v-if="rejectingId === transport.id" class="mt-3 flex gap-2">
          <input
            v-model="rejectReason"
            type="text"
            placeholder="Alasan penolakan"
            class="field-input flex-1"
          >
          <button
            class="btn-primary !px-3 !py-1.5"
            @click="confirmReject(transport)"
          >
            Kirim
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
