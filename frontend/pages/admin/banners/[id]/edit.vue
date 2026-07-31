<script setup lang="ts">
import type { Banner, BannerFormPayload } from '~/types/banner'

definePageMeta({ role: 'admin' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const bannerId = route.params.id as string

const banner = ref<Banner | null>(null)
const loading = ref(true)
const submitting = ref(false)
const uploading = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<BannerFormPayload>({
  title: '',
  link_url: '',
  sort_order: 0,
})

async function loadBanner() {
  loading.value = true
  const response = await api<{ data: Banner }>(`/api/admin/banners/${bannerId}`)
  banner.value = response.data
  form.value = {
    title: response.data.title,
    link_url: response.data.link_url ?? '',
    sort_order: response.data.sort_order,
  }
  loading.value = false
}

async function handleSubmit() {
  errors.value = {}
  submitting.value = true

  try {
    const response = await api<{ data: Banner }>(`/api/admin/banners/${bannerId}`, { method: 'PATCH', body: form.value })
    banner.value = response.data
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
    const response = await api<{ data: Banner }>(`/api/admin/banners/${bannerId}/image`, { method: 'POST', body })
    banner.value = response.data
  } catch {
    alert('Gagal mengunggah gambar banner.')
  } finally {
    uploading.value = false
    input.value = ''
  }
}

async function deleteBanner() {
  if (!banner.value || !confirm(`Hapus banner "${banner.value.title}"?`)) return

  await api(`/api/admin/banners/${bannerId}`, { method: 'DELETE' })
  router.push('/admin/banners')
}

onMounted(loadBanner)
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <p v-if="loading">Memuat...</p>

    <template v-else-if="banner">
      <div class="flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-gray-900">Edit Banner</h1>
        <span class="badge" :class="banner.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'">
          {{ banner.is_active ? 'Aktif' : 'Nonaktif' }}
        </span>
      </div>

      <div class="mt-4">
        <button class="btn-danger-outline" @click="deleteBanner">
          Hapus Banner
        </button>
      </div>

      <div class="mt-6">
        <span class="field-label">Gambar Banner</span>
        <div class="mt-2">
          <img
            v-if="banner.image"
            :src="banner.image"
            class="h-40 w-full rounded-xl object-cover sm:w-96"
            alt=""
          >
          <div v-else class="flex h-40 w-full items-center justify-center rounded-xl bg-gray-100 text-sm text-gray-400 sm:w-96">
            Belum ada gambar — banner tidak akan tampil di halaman utama sampai gambar diunggah
          </div>
        </div>
        <label class="mt-3 inline-block cursor-pointer text-sm text-brand-brown underline">
          {{ uploading ? 'Mengunggah...' : (banner.image ? '+ Ganti Gambar' : '+ Unggah Gambar') }}
          <input type="file" accept="image/*" class="hidden" :disabled="uploading" @change="handleImageUpload">
        </label>
      </div>

      <div class="mt-6">
        <BannerForm
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
