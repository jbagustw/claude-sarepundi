<script setup lang="ts">
import type { SiteSettings } from '~/types/siteSettings'

definePageMeta({ role: 'admin' })

const api = useApi()
const loading = ref(true)
const saving = ref(false)
const uploadingHero = ref(false)
const removingHero = ref(false)
const errors = ref<Record<string, string[]>>({})
const savedMessage = ref('')

const heroImageUrl = ref<string | null>(null)

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
  heroImageUrl.value = response.data.hero_image_url
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

async function handleHeroUpload(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  uploadingHero.value = true
  try {
    const body = new FormData()
    body.append('image', file)
    const response = await api<{ data: SiteSettings }>('/api/admin/site-settings/hero-image', { method: 'POST', body })
    heroImageUrl.value = response.data.hero_image_url
  } catch {
    alert('Gagal mengunggah gambar hero banner.')
  } finally {
    uploadingHero.value = false
    input.value = ''
  }
}

async function removeHeroImage() {
  if (!confirm('Hapus gambar hero banner? Homepage akan kembali memakai gambar bawaan.')) return

  removingHero.value = true
  try {
    const response = await api<{ data: SiteSettings }>('/api/admin/site-settings/hero-image', { method: 'DELETE' })
    heroImageUrl.value = response.data.hero_image_url
  } catch {
    alert('Gagal menghapus gambar hero banner.')
  } finally {
    removingHero.value = false
  }
}

onMounted(loadSettings)
</script>

<template>
  <div class="mx-auto max-w-lg">
    <h1 class="font-display text-2xl font-bold text-gray-900">Pengaturan Website</h1>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>

    <template v-else>
      <div class="mt-6">
        <h2 class="text-sm font-semibold text-gray-900">Hero Banner Homepage</h2>
        <p class="mt-1 text-sm text-gray-600">
          Gambar latar besar di paling atas homepage. Kosongkan (hapus) untuk kembali memakai gambar bawaan. Rekomendasi: foto landscape minimal 1920×1080, di bawah 6MB.
        </p>

        <div class="mt-3">
          <img
            v-if="heroImageUrl"
            :src="heroImageUrl"
            class="h-40 w-full rounded-xl object-cover"
            alt=""
          >
          <div v-else class="flex h-40 w-full items-center justify-center rounded-xl bg-gray-100 text-sm text-gray-400">
            Belum ada gambar kustom — homepage memakai gambar bawaan
          </div>
        </div>

        <div class="mt-3 flex items-center gap-4">
          <label class="cursor-pointer text-sm text-brand-brown underline">
            {{ uploadingHero ? 'Mengunggah...' : (heroImageUrl ? '+ Ganti Gambar' : '+ Unggah Gambar') }}
            <input type="file" accept="image/*" class="hidden" :disabled="uploadingHero" @change="handleHeroUpload">
          </label>
          <button
            v-if="heroImageUrl"
            type="button"
            class="text-sm text-red-600 underline disabled:opacity-50"
            :disabled="removingHero"
            @click="removeHeroImage"
          >
            {{ removingHero ? 'Menghapus...' : 'Hapus Gambar' }}
          </button>
        </div>
      </div>

      <div class="mt-8 border-t border-gray-200 pt-6">
        <h2 class="text-sm font-semibold text-gray-900">Media Sosial</h2>
        <p class="mt-1 text-sm text-gray-600">
          Link ini akan ditampilkan sebagai ikon aktif di footer website. Kosongkan jika belum ada, ikon akan tetap tampil tapi nonaktif.
        </p>

        <form class="mt-4 space-y-4" @submit.prevent="saveSettings">
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
  </div>
</template>
