<script setup lang="ts">
definePageMeta({ role: 'user' })

const authStore = useAuthStore()
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-gray-900">
      Selamat datang, {{ authStore.user?.name }}
    </h1>
    <p class="mt-2 text-gray-600">
      Dashboard pencari villa. Wishlist akan tampil di sini.
    </p>

    <div class="mt-6 flex gap-3">
      <NuxtLink to="/villas" class="btn-primary">
        Cari Villa
      </NuxtLink>
      <NuxtLink to="/user/bookings" class="btn-outline">
        Riwayat Booking
      </NuxtLink>
    </div>
  </div>
</template>
