<script setup lang="ts">
import type { GatheringVenueFormPayload } from '~/types/gatheringVenue'
import type { Facility } from '~/types/villa'

definePageMeta({ role: 'mitra' })

const api = useApi()
const router = useRouter()

const facilities = ref<Facility[]>([])
const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<GatheringVenueFormPayload>({
  name: '',
  description: '',
  address: '',
  city: '',
  province: '',
  capacity: 50,
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
    const response = await api<{ data: { id: number } }>('/api/mitra/gathering-venues', {
      method: 'POST',
      body: form.value,
    })
    router.push(`/mitra/gathering-venues/${response.data.id}/edit`)
  } catch (error: any) {
    if (error?.data?.errors) errors.value = error.data.errors
    else alert('Gagal membuat lokasi gathering.')
  } finally {
    submitting.value = false
  }
}

onMounted(loadFacilities)
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <h1 class="font-display text-2xl font-bold text-gray-900">Tambah Lokasi Gathering</h1>
    <p class="mt-1 text-sm text-gray-600">
      Lokasi baru akan tersimpan sebagai draft. Tambahkan foto dan slot waktu, lalu kirim untuk direview admin.
    </p>

    <div class="mt-6">
      <GatheringVenueForm
        v-model="form"
        :facilities="facilities"
        :submitting="submitting"
        :errors="errors"
        submit-label="Simpan Lokasi"
        @submit="handleSubmit"
      />
    </div>
  </div>
</template>
