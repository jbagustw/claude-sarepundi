<script setup lang="ts">
import type { Glamping } from '~/types/glamping'

definePageMeta({ role: 'mitra' })

const api = useApi()
const glampings = ref<Glamping[]>([])
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

async function loadGlampings() {
  loading.value = true
  try {
    const response = await api<{ data: Glamping[] }>('/api/mitra/glampings')
    glampings.value = response.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar glamping.'
  } finally {
    loading.value = false
  }
}

async function submitForReview(glamping: Glamping) {
  try {
    await api(`/api/mitra/glampings/${glamping.id}/submit`, { method: 'POST' })
    await loadGlampings()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mengirim glamping untuk direview.')
  }
}

async function deleteGlamping(glamping: Glamping) {
  if (!confirm(`Hapus glamping "${glamping.name}"?`)) return

  try {
    await api(`/api/mitra/glampings/${glamping.id}`, { method: 'DELETE' })
    await loadGlampings()
  } catch {
    alert('Gagal menghapus glamping.')
  }
}

onMounted(loadGlampings)
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="font-display text-2xl font-bold text-gray-900">Glamping Saya</h1>
      <NuxtLink to="/mitra/glampings/create" class="btn-primary">
        + Tambah Glamping
      </NuxtLink>
    </div>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="errorMessage" class="mt-6 text-red-600">{{ errorMessage }}</p>
    <p v-else-if="glampings.length === 0" class="mt-6 text-gray-600">
      Belum ada glamping. Klik "Tambah Glamping" untuk mulai.
    </p>

    <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="glamping in glampings" :key="glamping.id" class="card p-4">
        <div class="flex items-start justify-between">
          <h2 class="font-display font-semibold text-gray-900">{{ glamping.name }}</h2>
          <span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusColor[glamping.status]">
            {{ statusLabel[glamping.status] }}
          </span>
        </div>
        <p class="mt-1 text-sm text-gray-600">{{ glamping.city }}</p>
        <p class="mt-1 text-sm font-medium text-gray-900">{{ formatRupiah(glamping.base_price) }} / malam</p>

        <p v-if="glamping.status === 'rejected' && glamping.rejection_reason" class="mt-2 text-sm text-red-600">
          Alasan ditolak: {{ glamping.rejection_reason }}
        </p>

        <div class="mt-3 flex flex-wrap gap-2 text-sm">
          <NuxtLink :to="`/mitra/glampings/${glamping.id}/edit`" class="text-brand-brown underline">
            Edit
          </NuxtLink>
          <button
            v-if="glamping.status === 'draft' || glamping.status === 'rejected'"
            class="text-green-700 underline"
            @click="submitForReview(glamping)"
          >
            Kirim untuk Review
          </button>
          <button class="text-red-600 underline" @click="deleteGlamping(glamping)">
            Hapus
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
