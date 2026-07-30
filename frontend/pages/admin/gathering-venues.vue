<script setup lang="ts">
import type { GatheringVenue } from '~/types/gatheringVenue'

definePageMeta({ role: 'admin' })

const api = useApi()
const venues = ref<GatheringVenue[]>([])
const loading = ref(true)
const rejectingId = ref<number | null>(null)
const rejectReason = ref('')

async function loadPending() {
  loading.value = true
  const response = await api<{ data: GatheringVenue[] }>('/api/admin/gathering-venues', {
    query: { status: 'pending_review' },
  })
  venues.value = response.data
  loading.value = false
}

async function approve(venue: GatheringVenue) {
  await api(`/api/admin/gathering-venues/${venue.id}/approve`, { method: 'POST' })
  await loadPending()
}

function openReject(venue: GatheringVenue) {
  rejectingId.value = venue.id
  rejectReason.value = ''
}

async function confirmReject(venue: GatheringVenue) {
  if (!rejectReason.value.trim()) return

  await api(`/api/admin/gathering-venues/${venue.id}/reject`, {
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
    <h1 class="font-display text-2xl font-bold text-gray-900">Moderasi Lokasi Gathering</h1>
    <p class="mt-1 text-sm text-gray-600">Lokasi gathering yang menunggu review sebelum dipublikasikan.</p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="venues.length === 0" class="mt-6 text-gray-600">Tidak ada lokasi yang menunggu review.</p>

    <div v-else class="mt-6 space-y-4">
      <div v-for="venue in venues" :key="venue.id" class="card p-4">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="font-display font-semibold text-gray-900">{{ venue.name }}</h2>
            <p class="text-sm text-gray-600">{{ venue.city }} &middot; {{ venue.mitra.business_name }}</p>
            <p class="text-sm font-medium text-gray-900">Kapasitas {{ venue.capacity }} orang &middot; {{ venue.slots.length }} slot</p>
          </div>
          <div class="flex gap-2 text-sm">
            <button
              class="rounded-full bg-green-700 px-3 py-1.5 text-white hover:bg-green-800"
              @click="approve(venue)"
            >
              Setujui
            </button>
            <button
              class="rounded-full bg-red-600 px-3 py-1.5 text-white hover:bg-red-700"
              @click="openReject(venue)"
            >
              Tolak
            </button>
          </div>
        </div>

        <p v-if="venue.description" class="mt-2 text-sm text-gray-600">{{ venue.description }}</p>

        <div v-if="venue.slots.length" class="mt-2 flex flex-wrap gap-2 text-xs text-gray-600">
          <span v-for="slot in venue.slots" :key="slot.id" class="chip">
            {{ slot.name }} ({{ slot.start_time }}-{{ slot.end_time }}) &middot; {{ formatRupiah(slot.price) }}
          </span>
        </div>

        <div class="mt-2 flex flex-wrap gap-2">
          <img
            v-for="image in venue.images"
            :key="image.id"
            :src="image.url"
            class="h-16 w-16 rounded object-cover"
            alt=""
          >
        </div>

        <div v-if="rejectingId === venue.id" class="mt-3 flex gap-2">
          <input
            v-model="rejectReason"
            type="text"
            placeholder="Alasan penolakan"
            class="field-input flex-1"
          >
          <button
            class="btn-primary !px-3 !py-1.5"
            @click="confirmReject(venue)"
          >
            Kirim
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
