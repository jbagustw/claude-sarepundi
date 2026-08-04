<script setup lang="ts">
import type { GatheringVenue } from '~/types/gatheringVenue'

definePageMeta({ role: 'mitra' })

const api = useApi()
const venues = ref<GatheringVenue[]>([])
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

async function loadVenues() {
  loading.value = true
  try {
    const response = await api<{ data: GatheringVenue[] }>('/api/mitra/gathering-venues')
    venues.value = response.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar gathering venue.'
  } finally {
    loading.value = false
  }
}

async function submitForReview(venue: GatheringVenue) {
  try {
    await api(`/api/mitra/gathering-venues/${venue.id}/submit`, { method: 'POST' })
    await loadVenues()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mengirim lokasi untuk direview.')
  }
}

async function deleteVenue(venue: GatheringVenue) {
  if (!confirm(`Hapus lokasi "${venue.name}"?`)) return

  try {
    await api(`/api/mitra/gathering-venues/${venue.id}`, { method: 'DELETE' })
    await loadVenues()
  } catch {
    alert('Gagal menghapus lokasi.')
  }
}

onMounted(loadVenues)
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="font-display text-2xl font-bold text-gray-900">Gathering Venue Saya</h1>
      <NuxtLink to="/mitra/gathering-venues/create" class="btn-primary">
        + Tambah Gathering Venue
      </NuxtLink>
    </div>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="errorMessage" class="mt-6 text-red-600">{{ errorMessage }}</p>
    <p v-else-if="venues.length === 0" class="mt-6 text-gray-600">
      Belum ada gathering venue. Klik "Tambah Gathering Venue" untuk mulai.
    </p>

    <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="venue in venues" :key="venue.id" class="card p-4">
        <div class="flex items-start justify-between">
          <h2 class="font-display font-semibold text-gray-900">{{ venue.name }}</h2>
          <span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusColor[venue.status]">
            {{ statusLabel[venue.status] }}
          </span>
        </div>
        <p class="mt-1 text-sm text-gray-600">{{ venue.city }}</p>
        <p class="mt-1 text-sm font-medium text-gray-900">Kapasitas {{ venue.capacity }} orang</p>
        <p class="text-xs text-gray-500">{{ venue.slots.length }} slot terdaftar</p>

        <p v-if="venue.status === 'rejected' && venue.rejection_reason" class="mt-2 text-sm text-red-600">
          Alasan ditolak: {{ venue.rejection_reason }}
        </p>

        <div class="mt-3 flex flex-wrap gap-2 text-sm">
          <NuxtLink :to="`/mitra/gathering-venues/${venue.id}/edit`" class="text-brand-brown underline">
            Edit
          </NuxtLink>
          <button
            v-if="venue.status === 'draft' || venue.status === 'rejected'"
            class="text-green-700 underline"
            @click="submitForReview(venue)"
          >
            Kirim untuk Review
          </button>
          <button class="text-red-600 underline" @click="deleteVenue(venue)">
            Hapus
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
