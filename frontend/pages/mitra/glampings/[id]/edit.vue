<script setup lang="ts">
import type { GlampingFormPayload, Glamping } from '~/types/glamping'
import type { Facility } from '~/types/villa'

definePageMeta({ role: 'mitra' })

const route = useRoute()
const api = useApi()
const glampingId = route.params.id as string

const facilities = ref<Facility[]>([])
const glamping = ref<Glamping | null>(null)
const loading = ref(true)
const submitting = ref(false)
const uploading = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<GlampingFormPayload>({
  name: '',
  description: '',
  address: '',
  city: '',
  province: '',
  capacity_guest: 1,
  bedroom_count: 0,
  bathroom_count: 0,
  base_price: 0,
  facility_ids: [],
})

async function loadData() {
  loading.value = true
  const [facilityRes, glampingRes] = await Promise.all([
    api<{ data: Facility[] }>('/api/facilities'),
    api<{ data: Glamping }>(`/api/mitra/glampings/${glampingId}`),
  ])

  facilities.value = facilityRes.data
  glamping.value = glampingRes.data
  form.value = {
    name: glampingRes.data.name,
    description: glampingRes.data.description ?? '',
    address: glampingRes.data.address ?? '',
    city: glampingRes.data.city,
    province: glampingRes.data.province ?? '',
    capacity_guest: glampingRes.data.capacity_guest,
    bedroom_count: glampingRes.data.bedroom_count,
    bathroom_count: glampingRes.data.bathroom_count,
    base_price: glampingRes.data.base_price,
    facility_ids: glampingRes.data.facilities.map((f) => f.id),
  }
  loading.value = false
}

async function handleSubmit() {
  errors.value = {}
  submitting.value = true

  try {
    const response = await api<{ data: Glamping }>(`/api/mitra/glampings/${glampingId}`, {
      method: 'PUT',
      body: form.value,
    })
    glamping.value = response.data
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
    await api(`/api/mitra/glampings/${glampingId}/images`, { method: 'POST', body })
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
  await api(`/api/mitra/glampings/${glampingId}/images/${imageId}`, { method: 'DELETE' })
  await loadData()
}

async function submitForReview() {
  try {
    await api(`/api/mitra/glampings/${glampingId}/submit`, { method: 'POST' })
    await loadData()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mengirim glamping untuk direview.')
  }
}

onMounted(loadData)
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <p v-if="loading">Memuat...</p>

    <template v-else-if="glamping">
      <div class="flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-gray-900">Edit Glamping</h1>
        <button
          v-if="glamping.status === 'draft' || glamping.status === 'rejected'"
          class="rounded-full bg-green-700 px-4 py-2 text-sm text-white hover:bg-green-800"
          @click="submitForReview"
        >
          Kirim untuk Review
        </button>
      </div>

      <p v-if="glamping.status === 'rejected' && glamping.rejection_reason" class="mt-3 rounded bg-red-50 p-3 text-sm text-red-700">
        Alasan ditolak admin: {{ glamping.rejection_reason }}
      </p>

      <div class="mt-6">
        <span class="block text-sm font-medium text-gray-700">Foto Glamping</span>
        <div class="mt-2 flex flex-wrap gap-3">
          <div v-for="image in glamping.images" :key="image.id" class="relative">
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
        <GlampingForm
          v-model="form"
          :facilities="facilities"
          :submitting="submitting"
          :errors="errors"
          submit-label="Simpan Perubahan"
          @submit="handleSubmit"
        />
      </div>
    </template>
  </div>
</template>
