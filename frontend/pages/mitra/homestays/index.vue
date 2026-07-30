<script setup lang="ts">
import type { Homestay } from '~/types/homestay'

definePageMeta({ role: 'mitra' })

const api = useApi()
const homestays = ref<Homestay[]>([])
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

async function loadHomestays() {
  loading.value = true
  try {
    const response = await api<{ data: Homestay[] }>('/api/mitra/homestays')
    homestays.value = response.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar homestay.'
  } finally {
    loading.value = false
  }
}

async function submitForReview(homestay: Homestay) {
  try {
    await api(`/api/mitra/homestays/${homestay.id}/submit`, { method: 'POST' })
    await loadHomestays()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mengirim homestay untuk direview.')
  }
}

async function deleteHomestay(homestay: Homestay) {
  if (!confirm(`Hapus homestay "${homestay.name}"?`)) return

  try {
    await api(`/api/mitra/homestays/${homestay.id}`, { method: 'DELETE' })
    await loadHomestays()
  } catch {
    alert('Gagal menghapus homestay.')
  }
}

onMounted(loadHomestays)
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="font-display text-2xl font-bold text-gray-900">Homestay Saya</h1>
      <NuxtLink to="/mitra/homestays/create" class="btn-primary">
        + Tambah Homestay
      </NuxtLink>
    </div>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="errorMessage" class="mt-6 text-red-600">{{ errorMessage }}</p>
    <p v-else-if="homestays.length === 0" class="mt-6 text-gray-600">
      Belum ada homestay. Klik "Tambah Homestay" untuk mulai.
    </p>

    <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="homestay in homestays" :key="homestay.id" class="card p-4">
        <div class="flex items-start justify-between">
          <h2 class="font-display font-semibold text-gray-900">{{ homestay.name }}</h2>
          <span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusColor[homestay.status]">
            {{ statusLabel[homestay.status] }}
          </span>
        </div>
        <p class="mt-1 text-sm text-gray-600">{{ homestay.city }}</p>
        <p class="mt-1 text-sm font-medium text-gray-900">{{ formatRupiah(homestay.base_price) }} / malam</p>

        <p v-if="homestay.status === 'rejected' && homestay.rejection_reason" class="mt-2 text-sm text-red-600">
          Alasan ditolak: {{ homestay.rejection_reason }}
        </p>

        <div class="mt-3 flex flex-wrap gap-2 text-sm">
          <NuxtLink :to="`/mitra/homestays/${homestay.id}/edit`" class="text-brand-brown underline">
            Edit
          </NuxtLink>
          <button
            v-if="homestay.status === 'draft' || homestay.status === 'rejected'"
            class="text-green-700 underline"
            @click="submitForReview(homestay)"
          >
            Kirim untuk Review
          </button>
          <button class="text-red-600 underline" @click="deleteHomestay(homestay)">
            Hapus
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
