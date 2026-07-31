<script setup lang="ts">
import type { Transport } from '~/types/transport'

definePageMeta({ role: 'mitra' })

const api = useApi()
const transports = ref<Transport[]>([])
const loading = ref(true)
const errorMessage = ref('')

const statusLabel: Record<string, string> = {
  draft: 'Draft',
  pending_review: 'Menunggu Review',
  published: 'Dipublikasikan',
  rejected: 'Ditolak',
  inactive: 'Nonaktif',
}

const statusColor: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  pending_review: 'bg-yellow-100 text-yellow-800',
  published: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
  inactive: 'bg-gray-100 text-gray-500',
}

async function loadTransports() {
  loading.value = true
  try {
    const response = await api<{ data: Transport[] }>('/api/mitra/transports')
    transports.value = response.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar transport.'
  } finally {
    loading.value = false
  }
}

async function submitForReview(transport: Transport) {
  try {
    await api(`/api/mitra/transports/${transport.id}/submit`, { method: 'POST' })
    await loadTransports()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mengirim transport untuk direview.')
  }
}

async function deleteTransport(transport: Transport) {
  if (!confirm(`Hapus transport "${transport.name}"?`)) return

  try {
    await api(`/api/mitra/transports/${transport.id}`, { method: 'DELETE' })
    await loadTransports()
  } catch {
    alert('Gagal menghapus transport.')
  }
}

onMounted(loadTransports)
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="font-display text-2xl font-bold text-gray-900">Transport Saya</h1>
      <NuxtLink to="/mitra/transports/create" class="btn-primary">
        + Tambah Transport
      </NuxtLink>
    </div>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="errorMessage" class="mt-6 text-red-600">{{ errorMessage }}</p>
    <p v-else-if="transports.length === 0" class="mt-6 text-gray-600">
      Belum ada transport. Klik "Tambah Transport" untuk mulai.
    </p>

    <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="transport in transports" :key="transport.id" class="card p-4">
        <div class="flex items-start justify-between">
          <h2 class="font-display font-semibold text-gray-900">{{ transport.name }}</h2>
          <span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusColor[transport.status]">
            {{ statusLabel[transport.status] }}
          </span>
        </div>
        <p class="mt-1 text-sm text-gray-600">{{ transport.vehicle_type }} &middot; {{ transport.city }}</p>
        <p v-if="transport.price_per_day_self_drive" class="mt-1 text-sm text-gray-900">
          Lepas kunci: {{ formatRupiah(transport.price_per_day_self_drive) }}/hari
        </p>
        <p v-if="transport.price_per_day_with_driver" class="text-sm text-gray-900">
          Dengan supir: {{ formatRupiah(transport.price_per_day_with_driver) }}/hari
        </p>

        <p v-if="transport.status === 'rejected' && transport.rejection_reason" class="mt-2 text-sm text-red-600">
          Alasan ditolak: {{ transport.rejection_reason }}
        </p>

        <div class="mt-3 flex flex-wrap gap-2 text-sm">
          <NuxtLink :to="`/mitra/transports/${transport.id}/edit`" class="text-brand-brown underline">
            Edit
          </NuxtLink>
          <button
            v-if="transport.status === 'draft' || transport.status === 'rejected'"
            class="text-green-700 underline"
            @click="submitForReview(transport)"
          >
            Kirim untuk Review
          </button>
          <button class="text-red-600 underline" @click="deleteTransport(transport)">
            Hapus
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
