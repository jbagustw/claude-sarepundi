<script setup lang="ts">
const open = ref(false)

const categories = [
  { to: '/villas', label: 'Villa' },
  { to: '/homestays', label: 'Homestay' },
  { to: '/gathering-venues', label: 'Lokasi Gathering' },
]
</script>

<template>
  <div class="relative">
    <button
      class="flex items-center gap-1 text-sm font-medium text-gray-600 hover:text-brand-brown"
      @click="open = !open"
    >
      Cari
      <span aria-hidden="true" class="text-xs">▾</span>
    </button>

    <div
      v-if="open"
      class="card absolute left-0 z-10 mt-2 w-48 overflow-hidden py-1"
    >
      <NuxtLink
        v-for="category in categories"
        :key="category.to"
        :to="category.to"
        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        @click="open = false"
      >
        {{ category.label }}
      </NuxtLink>
    </div>
  </div>
</template>
