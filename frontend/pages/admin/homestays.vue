<script setup lang="ts">
import type { Homestay } from '~/types/homestay'

definePageMeta({ role: 'admin' })

const api = useApi()
const homestays = ref<Homestay[]>([])
const loading = ref(true)
const rejectingId = ref<number | null>(null)
const rejectReason = ref('')

async function loadPending() {
  loading.value = true
  const response = await api<{ data: Homestay[] }>('/api/admin/homestays', {
    query: { status: 'pending_review' },
  })
  homestays.value = response.data
  loading.value = false
}

async function approve(homestay: Homestay) {
  await api(`/api/admin/homestays/${homestay.id}/approve`, { method: 'POST' })
  await loadPending()
}

function openReject(homestay: Homestay) {
  rejectingId.value = homestay.id
  rejectReason.value = ''
}

async function confirmReject(homestay: Homestay) {
  if (!rejectReason.value.trim()) return

  await api(`/api/admin/homestays/${homestay.id}/reject`, {
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
    <h1 class="font-display text-2xl font-bold text-gray-900">Moderasi Homestay</h1>
    <p class="mt-1 text-sm text-gray-600">Homestay yang menunggu review sebelum dipublikasikan.</p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="homestays.length === 0" class="mt-6 text-gray-600">Tidak ada homestay yang menunggu review.</p>

    <div v-else class="mt-6 space-y-4">
      <div v-for="homestay in homestays" :key="homestay.id" class="card p-4">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="font-display font-semibold text-gray-900">{{ homestay.name }}</h2>
            <p class="text-sm text-gray-600">{{ homestay.city }} &middot; {{ homestay.mitra.business_name }}</p>
            <p class="text-sm font-medium text-gray-900">{{ formatRupiah(homestay.base_price) }} / malam</p>
          </div>
          <div class="flex gap-2 text-sm">
            <button
              class="rounded-full bg-green-700 px-3 py-1.5 text-white hover:bg-green-800"
              @click="approve(homestay)"
            >
              Setujui
            </button>
            <button
              class="rounded-full bg-red-600 px-3 py-1.5 text-white hover:bg-red-700"
              @click="openReject(homestay)"
            >
              Tolak
            </button>
          </div>
        </div>

        <p v-if="homestay.description" class="mt-2 text-sm text-gray-600">{{ homestay.description }}</p>

        <div class="mt-2 flex flex-wrap gap-2">
          <img
            v-for="image in homestay.images"
            :key="image.id"
            :src="image.url"
            class="h-16 w-16 rounded object-cover"
            alt=""
          >
        </div>

        <div v-if="rejectingId === homestay.id" class="mt-3 flex gap-2">
          <input
            v-model="rejectReason"
            type="text"
            placeholder="Alasan penolakan"
            class="field-input flex-1"
          >
          <button
            class="btn-primary !px-3 !py-1.5"
            @click="confirmReject(homestay)"
          >
            Kirim
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
