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
      Dashboard pencari villa. Riwayat booking &amp; wishlist akan tampil di sini.
    </p>
  </div>
</template>
