<script setup lang="ts">
import type { Glamping } from '~/types/glamping'

definePageMeta({ role: 'admin' })

const api = useApi()
const glampings = ref<Glamping[]>([])
const loading = ref(true)
const rejectingId = ref<number | null>(null)
const rejectReason = ref('')

async function loadPending() {
  loading.value = true
  const response = await api<{ data: Glamping[] }>('/api/admin/glampings', {
    query: { status: 'pending_review' },
  })
  glampings.value = response.data
  loading.value = false
}

async function approve(glamping: Glamping) {
  await api(`/api/admin/glampings/${glamping.id}/approve`, { method: 'POST' })
  await loadPending()
}

function openReject(glamping: Glamping) {
  rejectingId.value = glamping.id
  rejectReason.value = ''
}

async function confirmReject(glamping: Glamping) {
  if (!rejectReason.value.trim()) return

  await api(`/api/admin/glampings/${glamping.id}/reject`, {
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
    <h1 class="font-display text-2xl font-bold text-gray-900">Moderasi Glamping</h1>
    <p class="mt-1 text-sm text-gray-600">Glamping yang menunggu review sebelum dipublikasikan.</p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="glampings.length === 0" class="mt-6 text-gray-600">Tidak ada glamping yang menunggu review.</p>

    <div v-else class="mt-6 space-y-4">
      <div v-for="glamping in glampings" :key="glamping.id" class="card p-4">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="font-display font-semibold text-gray-900">{{ glamping.name }}</h2>
            <p class="text-sm text-gray-600">{{ glamping.city }} &middot; {{ glamping.mitra.business_name }}</p>
            <p class="text-sm font-medium text-gray-900">{{ formatRupiah(glamping.base_price) }} / malam</p>
          </div>
          <div class="flex gap-2 text-sm">
            <button
              class="rounded-full bg-green-700 px-3 py-1.5 text-white hover:bg-green-800"
              @click="approve(glamping)"
            >
              Setujui
            </button>
            <button
              class="rounded-full bg-red-600 px-3 py-1.5 text-white hover:bg-red-700"
              @click="openReject(glamping)"
            >
              Tolak
            </button>
          </div>
        </div>

        <p v-if="glamping.description" class="mt-2 text-sm text-gray-600">{{ glamping.description }}</p>

        <div class="mt-2 flex flex-wrap gap-2">
          <img
            v-for="image in glamping.images"
            :key="image.id"
            :src="image.url"
            class="h-16 w-16 rounded object-cover"
            alt=""
          >
        </div>

        <div v-if="rejectingId === glamping.id" class="mt-3 flex gap-2">
          <input
            v-model="rejectReason"
            type="text"
            placeholder="Alasan penolakan"
            class="field-input flex-1"
          >
          <button
            class="btn-primary !px-3 !py-1.5"
            @click="confirmReject(glamping)"
          >
            Kirim
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
