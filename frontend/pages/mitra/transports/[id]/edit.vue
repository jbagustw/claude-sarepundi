<script setup lang="ts">
import type { Transport, TransportFormPayload } from '~/types/transport'

definePageMeta({ role: 'mitra' })

const route = useRoute()
const api = useApi()
const transportId = route.params.id as string

const transport = ref<Transport | null>(null)
const loading = ref(true)
const submitting = ref(false)
const uploading = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<TransportFormPayload>({
  name: '',
  vehicle_type: '',
  description: '',
  capacity: 1,
  city: '',
  province: '',
  price_per_day_self_drive: null,
  price_per_day_with_driver: null,
})

async function loadData() {
  loading.value = true
  const response = await api<{ data: Transport }>(`/api/mitra/transports/${transportId}`)

  transport.value = response.data
  form.value = {
    name: response.data.name,
    vehicle_type: response.data.vehicle_type,
    description: response.data.description ?? '',
    capacity: response.data.capacity,
    city: response.data.city,
    province: response.data.province ?? '',
    price_per_day_self_drive: response.data.price_per_day_self_drive,
    price_per_day_with_driver: response.data.price_per_day_with_driver,
  }
  loading.value = false
}

async function handleSubmit() {
  errors.value = {}
  submitting.value = true

  try {
    const response = await api<{ data: Transport }>(`/api/mitra/transports/${transportId}`, {
      method: 'PUT',
      body: form.value,
    })
    transport.value = response.data
  } catch (error: any) {
    if (error?.data?.errors) errors.value = error.data.errors
    else alert('Gagal menyimpan perubahan.')
  } finally {
    submitting.value = false
  }
}

async function handleImageUpload(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  uploading.value = true
  try {
    const body = new FormData()
    body.append('image', file)
    await api(`/api/mitra/transports/${transportId}/images`, { method: 'POST', body })
    await loadData()
  } catch {
    alert('Gagal mengunggah foto.')
  } finally {
    uploading.value = false
    input.value = ''
  }
}

async function deleteImage(imageId: number) {
  if (!confirm('Hapus foto ini?')) return
  await api(`/api/mitra/transports/${transportId}/images/${imageId}`, { method: 'DELETE' })
  await loadData()
}

async function submitForReview() {
  try {
    await api(`/api/mitra/transports/${transportId}/submit`, { method: 'POST' })
    await loadData()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mengirim transport untuk direview.')
  }
}

onMounted(loadData)
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <p v-if="loading">Memuat...</p>

    <template v-else-if="transport">
      <div class="flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-gray-900">Edit Transport</h1>
        <button
          v-if="transport.status === 'draft' || transport.status === 'rejected'"
          class="rounded-full bg-green-700 px-4 py-2 text-sm text-white hover:bg-green-800"
          @click="submitForReview"
        >
          Kirim untuk Review
        </button>
      </div>

      <p v-if="transport.status === 'rejected' && transport.rejection_reason" class="mt-3 rounded bg-red-50 p-3 text-sm text-red-700">
        Alasan ditolak admin: {{ transport.rejection_reason }}
      </p>

      <div class="mt-6">
        <span class="block text-sm font-medium text-gray-700">Foto Kendaraan</span>
        <div class="mt-2 flex flex-wrap gap-3">
          <div v-for="image in transport.images" :key="image.id" class="relative">
            <img :src="image.url" class="h-24 w-24 rounded object-cover" alt="">
            <span v-if="image.is_primary" class="absolute left-1 top-1 rounded bg-gray-900/80 px-1.5 py-0.5 text-[10px] text-white">
              Utama
            </span>
            <button
              class="absolute right-1 top-1 rounded bg-white/90 px-1.5 text-xs text-red-600"
              @click="deleteImage(image.id)"
            >
              &times;
            </button>
          </div>
        </div>
        <label class="mt-3 inline-block cursor-pointer text-sm text-brand-brown underline">
          {{ uploading ? 'Mengunggah...' : '+ Unggah Foto' }}
          <input type="file" accept="image/*" class="hidden" :disabled="uploading" @change="handleImageUpload">
        </label>
      </div>

      <div class="mt-6">
        <TransportForm
          v-model="form"
          :submitting="submitting"
          :errors="errors"
          submit-label="Simpan Perubahan"
          @submit="handleSubmit"
        />
      </div>
    </template>
  </div>
</template>
