<script setup lang="ts">
definePageMeta({ role: 'user' })

const authStore = useAuthStore()
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900">
      Selamat datang, {{ authStore.user?.name }}
    </h1>
    <p class="mt-2 text-gray-600">
      Dashboard pencari villa. Wishlist akan tampil di sini.
    </p>

    <div class="mt-6 flex gap-3">
      <NuxtLink to="/villas" class="rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700">
        Cari Villa
      </NuxtLink>
      <NuxtLink to="/user/bookings" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
        Riwayat Booking
      </NuxtLink>
    </div>
  </div>
</template>
