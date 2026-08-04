<script setup lang="ts">
import type { Apartment } from '~/types/apartment'

definePageMeta({ role: 'mitra' })

const api = useApi()
const apartments = ref<Apartment[]>([])
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

async function loadApartments() {
  loading.value = true
  try {
    const response = await api<{ data: Apartment[] }>('/api/mitra/apartments')
    apartments.value = response.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar apartment.'
  } finally {
    loading.value = false
  }
}

async function submitForReview(apartment: Apartment) {
  try {
    await api(`/api/mitra/apartments/${apartment.id}/submit`, { method: 'POST' })
    await loadApartments()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mengirim apartment untuk direview.')
  }
}

async function deleteApartment(apartment: Apartment) {
  if (!confirm(`Hapus apartment "${apartment.name}"?`)) return

  try {
    await api(`/api/mitra/apartments/${apartment.id}`, { method: 'DELETE' })
    await loadApartments()
  } catch {
    alert('Gagal menghapus apartment.')
  }
}

onMounted(loadApartments)
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="font-display text-2xl font-bold text-gray-900">Apartment Saya</h1>
      <NuxtLink to="/mitra/apartments/create" class="btn-primary">
        + Tambah Apartment
      </NuxtLink>
    </div>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="errorMessage" class="mt-6 text-red-600">{{ errorMessage }}</p>
    <p v-else-if="apartments.length === 0" class="mt-6 text-gray-600">
      Belum ada apartment. Klik "Tambah Apartment" untuk mulai.
    </p>

    <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="apartment in apartments" :key="apartment.id" class="card p-4">
        <div class="flex items-start justify-between">
          <h2 class="font-display font-semibold text-gray-900">{{ apartment.name }}</h2>
          <span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusColor[apartment.status]">
            {{ statusLabel[apartment.status] }}
          </span>
        </div>
        <p class="mt-1 text-sm text-gray-600">{{ apartment.city }}</p>
        <p class="mt-1 text-sm font-medium text-gray-900">{{ formatRupiah(apartment.base_price) }} / malam</p>

        <p v-if="apartment.status === 'rejected' && apartment.rejection_reason" class="mt-2 text-sm text-red-600">
          Alasan ditolak: {{ apartment.rejection_reason }}
        </p>

        <div class="mt-3 flex flex-wrap gap-2 text-sm">
          <NuxtLink :to="`/mitra/apartments/${apartment.id}/edit`" class="text-brand-brown underline">
            Edit
          </NuxtLink>
          <button
            v-if="apartment.status === 'draft' || apartment.status === 'rejected'"
            class="text-green-700 underline"
            @click="submitForReview(apartment)"
          >
            Kirim untuk Review
          </button>
          <button class="text-red-600 underline" @click="deleteApartment(apartment)">
            Hapus
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
