<script setup lang="ts">
definePageMeta({ role: 'admin' })

interface PendingMitra {
  id: number
  business_name: string
  business_address: string | null
  status: string
  user: { id: number; name: string; email: string; phone: string | null }
}

const api = useApi()
const mitras = ref<PendingMitra[]>([])
const loading = ref(true)

async function loadPending() {
  loading.value = true
  const response = await api<{ data: PendingMitra[] }>('/api/admin/mitras', {
    query: { status: 'pending' },
  })
  mitras.value = response.data
  loading.value = false
}

async function approve(mitra: PendingMitra) {
  await api(`/api/admin/mitras/${mitra.id}/approve`, { method: 'POST' })
  await loadPending()
}

async function reject(mitra: PendingMitra) {
  if (!confirm(`Tolak pendaftaran mitra "${mitra.business_name}"?`)) return
  await api(`/api/admin/mitras/${mitra.id}/reject`, { method: 'POST' })
  await loadPending()
}

onMounted(loadPending)
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900">Approval Mitra</h1>
    <p class="mt-1 text-sm text-gray-600">Pendaftaran mitra yang menunggu persetujuan.</p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="mitras.length === 0" class="mt-6 text-gray-600">Tidak ada pendaftaran mitra yang menunggu.</p>

    <div v-else class="mt-6 space-y-4">
      <div v-for="mitra in mitras" :key="mitra.id" class="flex items-start justify-between rounded border border-gray-200 bg-white p-4">
        <div>
          <h2 class="font-semibold text-gray-900">{{ mitra.business_name }}</h2>
          <p class="text-sm text-gray-600">{{ mitra.user.name }} &middot; {{ mitra.user.email }}</p>
          <p v-if="mitra.business_address" class="text-sm text-gray-500">{{ mitra.business_address }}</p>
        </div>
        <div class="flex gap-2 text-sm">
          <button class="rounded bg-green-700 px-3 py-1.5 text-white hover:bg-green-800" @click="approve(mitra)">
            Setujui
          </button>
          <button class="rounded bg-red-600 px-3 py-1.5 text-white hover:bg-red-700" @click="reject(mitra)">
            Tolak
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
