<script setup lang="ts">
import type { Villa } from '~/types/villa'

definePageMeta({ role: 'mitra' })

const api = useApi()
const villas = ref<Villa[]>([])
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

async function loadVillas() {
  loading.value = true
  try {
    const response = await api<{ data: Villa[] }>('/api/mitra/villas')
    villas.value = response.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar villa.'
  } finally {
    loading.value = false
  }
}

async function submitForReview(villa: Villa) {
  try {
    await api(`/api/mitra/villas/${villa.id}/submit`, { method: 'POST' })
    await loadVillas()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mengirim villa untuk direview.')
  }
}

async function deleteVilla(villa: Villa) {
  if (!confirm(`Hapus villa "${villa.name}"?`)) return

  try {
    await api(`/api/mitra/villas/${villa.id}`, { method: 'DELETE' })
    await loadVillas()
  } catch {
    alert('Gagal menghapus villa.')
  }
}

onMounted(loadVillas)
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Villa Saya</h1>
      <NuxtLink
        to="/mitra/villas/create"
        class="rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700"
      >
        + Tambah Villa
      </NuxtLink>
    </div>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="errorMessage" class="mt-6 text-red-600">{{ errorMessage }}</p>
    <p v-else-if="villas.length === 0" class="mt-6 text-gray-600">
      Belum ada villa. Klik "Tambah Villa" untuk mulai.
    </p>

    <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="villa in villas" :key="villa.id" class="rounded border border-gray-200 bg-white p-4">
        <div class="flex items-start justify-between">
          <h2 class="font-semibold text-gray-900">{{ villa.name }}</h2>
          <span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusColor[villa.status]">
            {{ statusLabel[villa.status] }}
          </span>
        </div>
        <p class="mt-1 text-sm text-gray-600">{{ villa.city }}</p>
        <p class="mt-1 text-sm font-medium text-gray-900">{{ formatRupiah(villa.base_price) }} / malam</p>

        <p v-if="villa.status === 'rejected' && villa.rejection_reason" class="mt-2 text-sm text-red-600">
          Alasan ditolak: {{ villa.rejection_reason }}
        </p>

        <div class="mt-3 flex flex-wrap gap-2 text-sm">
          <NuxtLink :to="`/mitra/villas/${villa.id}/edit`" class="text-gray-700 underline">
            Edit
          </NuxtLink>
          <button
            v-if="villa.status === 'draft' || villa.status === 'rejected'"
            class="text-green-700 underline"
            @click="submitForReview(villa)"
          >
            Kirim untuk Review
          </button>
          <button class="text-red-600 underline" @click="deleteVilla(villa)">
            Hapus
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
