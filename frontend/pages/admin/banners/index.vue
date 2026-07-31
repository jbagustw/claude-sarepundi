<script setup lang="ts">
import type { Banner } from '~/types/banner'

definePageMeta({ role: 'admin' })

const api = useApi()
const banners = ref<Banner[]>([])
const loading = ref(true)
const actingOn = ref<number | null>(null)

async function loadBanners() {
  loading.value = true
  const response = await api<{ data: Banner[] }>('/api/admin/banners')
  banners.value = response.data
  loading.value = false
}

async function toggleActive(banner: Banner) {
  actingOn.value = banner.id
  try {
    await api(`/api/admin/banners/${banner.id}`, { method: 'PATCH', body: { is_active: !banner.is_active } })
    await loadBanners()
  } catch {
    alert('Gagal mengubah status banner.')
  } finally {
    actingOn.value = null
  }
}

async function deleteBanner(banner: Banner) {
  if (!confirm(`Hapus banner "${banner.title}"?`)) return

  actingOn.value = banner.id
  try {
    await api(`/api/admin/banners/${banner.id}`, { method: 'DELETE' })
    await loadBanners()
  } catch {
    alert('Gagal menghapus banner.')
  } finally {
    actingOn.value = null
  }
}

onMounted(loadBanners)
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="font-display text-2xl font-bold text-gray-900">Kelola Banner Iklan</h1>
      <NuxtLink to="/admin/banners/create" class="btn-primary">+ Buat Banner</NuxtLink>
    </div>
    <p class="mt-1 text-sm text-gray-600">
      Banner yang aktif dan sudah punya gambar akan tampil di halaman utama.
    </p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="banners.length === 0" class="mt-6 text-gray-600">Belum ada banner.</p>

    <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="banner in banners" :key="banner.id" class="card overflow-hidden">
        <img
          v-if="banner.image"
          :src="banner.image"
          class="h-32 w-full object-cover"
          alt=""
        >
        <div v-else class="flex h-32 w-full items-center justify-center bg-gray-100 text-sm text-gray-400">
          Belum ada gambar
        </div>
        <div class="p-3">
          <div class="flex items-center gap-2">
            <h2 class="font-display text-sm font-semibold text-gray-900">{{ banner.title }}</h2>
            <span class="badge" :class="banner.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'">
              {{ banner.is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
          </div>
          <p v-if="banner.link_url" class="mt-1 truncate text-xs text-gray-500">&rarr; {{ banner.link_url }}</p>

          <div class="mt-3 flex gap-2 text-sm">
            <NuxtLink :to="`/admin/banners/${banner.id}/edit`" class="btn-outline !px-3 !py-1">Edit</NuxtLink>
            <button
              class="rounded-full px-3 py-1 text-xs font-medium text-white disabled:opacity-50"
              :class="banner.is_active ? 'bg-gray-500 hover:bg-gray-600' : 'bg-green-700 hover:bg-green-800'"
              :disabled="actingOn === banner.id"
              @click="toggleActive(banner)"
            >
              {{ banner.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
            </button>
            <button
              class="rounded-full bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
              :disabled="actingOn === banner.id"
              @click="deleteBanner(banner)"
            >
              Hapus
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
