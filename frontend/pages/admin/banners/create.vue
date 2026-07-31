<script setup lang="ts">
import type { BannerFormPayload } from '~/types/banner'

definePageMeta({ role: 'admin' })

const api = useApi()
const router = useRouter()

const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<BannerFormPayload>({
  title: '',
  link_url: '',
  sort_order: 0,
})

async function handleSubmit() {
  errors.value = {}
  submitting.value = true

  try {
    const response = await api<{ data: { id: number } }>('/api/admin/banners', {
      method: 'POST',
      body: form.value,
    })
    router.push(`/admin/banners/${response.data.id}/edit`)
  } catch (error: any) {
    if (error?.data?.errors) errors.value = error.data.errors
    else alert('Gagal membuat banner.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <h1 class="font-display text-2xl font-bold text-gray-900">Buat Banner</h1>
    <p class="mt-1 text-sm text-gray-600">
      Banner baru akan tersimpan dulu, lalu unggah gambarnya di halaman berikutnya sebelum tampil di halaman utama.
    </p>

    <div class="mt-6">
      <BannerForm
        v-model="form"
        :submitting="submitting"
        :errors="errors"
        submit-label="Simpan Banner"
        @submit="handleSubmit"
      />
    </div>
  </div>
</template>
