<script setup lang="ts">
import type { ApartmentFormPayload } from '~/types/apartment'
import type { Facility } from '~/types/villa'

definePageMeta({ role: 'mitra' })

const api = useApi()
const router = useRouter()

const facilities = ref<Facility[]>([])
const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<ApartmentFormPayload>({
  name: '',
  description: '',
  address: '',
  city: '',
  province: '',
  capacity_guest: 2,
  bedroom_count: 1,
  bathroom_count: 1,
  base_price: 400000,
  facility_ids: [],
})

async function loadFacilities() {
  const response = await api<{ data: Facility[] }>('/api/facilities')
  facilities.value = response.data
}

async function handleSubmit() {
  errors.value = {}
  submitting.value = true

  try {
    const response = await api<{ data: { id: number } }>('/api/mitra/apartments', {
      method: 'POST',
      body: form.value,
    })
    router.push(`/mitra/apartments/${response.data.id}/edit`)
  } catch (error: any) {
    if (error?.data?.errors) errors.value = error.data.errors
    else alert('Gagal membuat apartment.')
  } finally {
    submitting.value = false
  }
}

onMounted(loadFacilities)
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <h1 class="font-display text-2xl font-bold text-gray-900">Tambah Apartment</h1>
    <p class="mt-1 text-sm text-gray-600">
      Apartment baru akan tersimpan sebagai draft. Tambahkan foto lalu kirim untuk direview admin.
    </p>

    <div class="mt-6">
      <ApartmentForm
        v-model="form"
        :facilities="facilities"
        :submitting="submitting"
        :errors="errors"
        submit-label="Simpan Apartment"
        @submit="handleSubmit"
      />
    </div>
  </div>
</template>
