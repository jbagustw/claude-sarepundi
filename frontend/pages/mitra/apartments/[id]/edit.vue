<script setup lang="ts">
import type { ApartmentFormPayload, Apartment } from '~/types/apartment'
import type { Facility } from '~/types/villa'

definePageMeta({ role: 'mitra' })

const route = useRoute()
const api = useApi()
const apartmentId = route.params.id as string

const facilities = ref<Facility[]>([])
const apartment = ref<Apartment | null>(null)
const loading = ref(true)
const submitting = ref(false)
const uploading = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<ApartmentFormPayload>({
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
  const [facilityRes, apartmentRes] = await Promise.all([
    api<{ data: Facility[] }>('/api/facilities'),
    api<{ data: Apartment }>(`/api/mitra/apartments/${apartmentId}`),
  ])

  facilities.value = facilityRes.data
  apartment.value = apartmentRes.data
  form.value = {
    name: apartmentRes.data.name,
    description: apartmentRes.data.description ?? '',
    address: apartmentRes.data.address ?? '',
    city: apartmentRes.data.city,
    province: apartmentRes.data.province ?? '',
    capacity_guest: apartmentRes.data.capacity_guest,
    bedroom_count: apartmentRes.data.bedroom_count,
    bathroom_count: apartmentRes.data.bathroom_count,
    base_price: apartmentRes.data.base_price,
    facility_ids: apartmentRes.data.facilities.map((f) => f.id),
  }
  loading.value = false
}

async function handleSubmit() {
  errors.value = {}
  submitting.value = true

  try {
    const response = await api<{ data: Apartment }>(`/api/mitra/apartments/${apartmentId}`, {
      method: 'PUT',
      body: form.value,
    })
    apartment.value = response.data
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
    await api(`/api/mitra/apartments/${apartmentId}/images`, { method: 'POST', body })
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
  await api(`/api/mitra/apartments/${apartmentId}/images/${imageId}`, { method: 'DELETE' })
  await loadData()
}

async function submitForReview() {
  try {
    await api(`/api/mitra/apartments/${apartmentId}/submit`, { method: 'POST' })
    await loadData()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mengirim apartment untuk direview.')
  }
}

onMounted(loadData)
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <p v-if="loading">Memuat...</p>

    <template v-else-if="apartment">
      <div class="flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-gray-900">Edit Apartment</h1>
        <button
          v-if="apartment.status === 'draft' || apartment.status === 'rejected'"
          class="rounded-full bg-green-700 px-4 py-2 text-sm text-white hover:bg-green-800"
          @click="submitForReview"
        >
          Kirim untuk Review
        </button>
      </div>

      <p v-if="apartment.status === 'rejected' && apartment.rejection_reason" class="mt-3 rounded bg-red-50 p-3 text-sm text-red-700">
        Alasan ditolak admin: {{ apartment.rejection_reason }}
      </p>

      <div class="mt-6">
        <span class="block text-sm font-medium text-gray-700">Foto Apartment</span>
        <div class="mt-2 flex flex-wrap gap-3">
          <div v-for="image in apartment.images" :key="image.id" class="relative">
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
        <ApartmentForm
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
