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

    <div class="mt-6 flex gap-3">
      <NuxtLink
        to="/admin/mitras"
        class="inline-block rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700"
      >
        Approval Mitra
      </NuxtLink>
      <NuxtLink
        to="/admin/villas"
        class="inline-block rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700"
      >
        Moderasi Villa
      </NuxtLink>
    </div>
  </div>
</template>
