<script setup lang="ts">
import type { TransportFormPayload } from '~/types/transport'

definePageMeta({ role: 'mitra' })

const api = useApi()
const router = useRouter()

const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<TransportFormPayload>({
  name: '',
  vehicle_type: '',
  description: '',
  capacity: 4,
  city: '',
  province: '',
  price_per_day_self_drive: null,
  price_per_day_with_driver: null,
})

async function handleSubmit() {
  errors.value = {}
  submitting.value = true

  try {
    const response = await api<{ data: { id: number } }>('/api/mitra/transports', {
      method: 'POST',
      body: form.value,
    })
    router.push(`/mitra/transports/${response.data.id}/edit`)
  } catch (error: any) {
    if (error?.data?.errors) errors.value = error.data.errors
    else alert('Gagal membuat transport.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <h1 class="font-display text-2xl font-bold text-gray-900">Tambah Transport</h1>
    <p class="mt-1 text-sm text-gray-600">
      Transport baru akan tersimpan sebagai draft. Tambahkan foto, lalu kirim untuk direview admin.
    </p>

    <div class="mt-6">
      <TransportForm
        v-model="form"
        :submitting="submitting"
        :errors="errors"
        submit-label="Simpan Transport"
        @submit="handleSubmit"
      />
    </div>
  </div>
</template>
