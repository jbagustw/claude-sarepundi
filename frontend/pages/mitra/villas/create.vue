<script setup lang="ts">
import type { Facility, VillaFormPayload } from '~/types/villa'

definePageMeta({ role: 'mitra' })

const api = useApi()
const router = useRouter()

const facilities = ref<Facility[]>([])
const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<VillaFormPayload>({
  name: '',
  description: '',
  address: '',
  city: '',
  province: '',
  capacity_guest: 2,
  bedroom_count: 1,
  bathroom_count: 1,
  base_price: 500000,
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
    const response = await api<{ data: { id: number } }>('/api/mitra/villas', {
      method: 'POST',
      body: form.value,
    })
    router.push(`/mitra/villas/${response.data.id}/edit`)
  } catch (error: any) {
    if (error?.data?.errors) errors.value = error.data.errors
    else alert('Gagal membuat villa.')
  } finally {
    submitting.value = false
  }
}

onMounted(loadFacilities)
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <h1 class="font-display text-2xl font-bold text-gray-900">Tambah Villa</h1>
    <p class="mt-1 text-sm text-gray-600">
      Villa baru akan tersimpan sebagai draft. Tambahkan foto lalu kirim untuk direview admin.
    </p>

    <div class="mt-6">
      <VillaForm
        v-model="form"
        :facilities="facilities"
        :submitting="submitting"
        :errors="errors"
        submit-label="Simpan Villa"
        @submit="handleSubmit"
      />
    </div>
  </div>
</template>
