<script setup lang="ts">
import type { SiteSettings } from '~/types/siteSettings'

definePageMeta({ role: 'admin' })

const api = useApi()
const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const savedMessage = ref('')

const form = reactive({
  instagram_url: '',
  facebook_url: '',
  tiktok_url: '',
})

async function loadSettings() {
  loading.value = true
  const response = await api<{ data: SiteSettings }>('/api/admin/site-settings')
  form.instagram_url = response.data.instagram_url ?? ''
  form.facebook_url = response.data.facebook_url ?? ''
  form.tiktok_url = response.data.tiktok_url ?? ''
  loading.value = false
}

async function saveSettings() {
  errors.value = {}
  savedMessage.value = ''
  saving.value = true

  try {
    await api('/api/admin/site-settings', {
      method: 'PATCH',
      body: {
        instagram_url: form.instagram_url || null,
        facebook_url: form.facebook_url || null,
        tiktok_url: form.tiktok_url || null,
      },
    })
    savedMessage.value = 'Link media sosial berhasil disimpan.'
  } catch (error: any) {
    if (error?.data?.errors) errors.value = error.data.errors
    else alert('Gagal menyimpan pengaturan.')
  } finally {
    saving.value = false
  }
}

onMounted(loadSettings)
</script>

<template>
  <div class="mx-auto max-w-lg">
    <h1 class="font-display text-2xl font-bold text-gray-900">Media Sosial</h1>
    <p class="mt-1 text-sm text-gray-600">
      Link ini akan ditampilkan sebagai ikon aktif di footer website. Kosongkan jika belum ada, ikon akan tetap tampil tapi nonaktif.
    </p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>

    <form v-else class="mt-6 space-y-4" @submit.prevent="saveSettings">
      <div>
        <label class="block text-sm font-medium text-gray-700" for="instagram_url">Instagram</label>
        <input
          id="instagram_url"
          v-model="form.instagram_url"
          type="url"
          placeholder="https://instagram.com/sarepundi"
          class="field-input mt-1"
        >
        <p v-if="errors.instagram_url" class="mt-1 text-sm text-red-600">{{ errors.instagram_url[0] }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700" for="facebook_url">Facebook</label>
        <input
          id="facebook_url"
          v-model="form.facebook_url"
          type="url"
          placeholder="https://facebook.com/sarepundi"
          class="field-input mt-1"
        >
        <p v-if="errors.facebook_url" class="mt-1 text-sm text-red-600">{{ errors.facebook_url[0] }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700" for="tiktok_url">TikTok</label>
        <input
          id="tiktok_url"
          v-model="form.tiktok_url"
          type="url"
          placeholder="https://tiktok.com/@sarepundi"
          class="field-input mt-1"
        >
        <p v-if="errors.tiktok_url" class="mt-1 text-sm text-red-600">{{ errors.tiktok_url[0] }}</p>
      </div>

      <p v-if="savedMessage" class="text-sm text-green-700">{{ savedMessage }}</p>

      <button
        type="submit"
        :disabled="saving"
        class="btn-primary"
      >
        {{ saving ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </form>
  </div>
</template>
