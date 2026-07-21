<script setup lang="ts">
const authStore = useAuthStore()
</script>

<template>
  <div class="text-center">
    <h1 class="text-3xl font-bold text-gray-900">Cari & Booking Villa Impianmu</h1>
    <p class="mx-auto mt-3 max-w-xl text-gray-600">
      Marketplace booking villa/penginapan — cari villa, kelola properti sebagai mitra,
      atau kelola platform sebagai admin.
    </p>

    <div v-if="!authStore.isAuthenticated" class="mt-8 flex justify-center gap-4">
      <NuxtLink to="/register" class="rounded bg-gray-900 px-5 py-2.5 text-white hover:bg-gray-700">
        Daftar Sekarang
      </NuxtLink>
      <NuxtLink to="/login" class="rounded border border-gray-300 px-5 py-2.5 text-gray-700 hover:bg-gray-100">
        Masuk
      </NuxtLink>
    </div>
  </div>
</template>
