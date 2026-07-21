<script setup lang="ts">
definePageMeta({ role: 'admin' })

const authStore = useAuthStore()
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900">
      Admin Panel — {{ authStore.user?.name }}
    </h1>
    <p class="mt-2 text-gray-600">
      Approval mitra, moderasi listing, monitoring transaksi, dan komisi akan
      dikelola dari sini.
    </p>
  </div>
</template>
