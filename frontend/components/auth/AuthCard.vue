<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <div class="mx-auto flex max-w-md items-center justify-center px-2 py-6 sm:py-10">
    <div class="relative w-full">
      <div class="absolute -left-3 -top-3 h-[97%] w-[97%] rounded-3xl bg-brand-brown-dark sm:-left-4 sm:-top-4" />
      <div class="absolute -bottom-3 -right-3 h-[97%] w-[97%] rounded-3xl bg-brand-sage/70 sm:-bottom-4 sm:-right-4" />

      <div class="card relative z-10 rounded-3xl p-6 sm:p-8">
        <h1 class="text-center font-display text-2xl font-bold text-gray-900 sm:text-3xl">{{ title }}</h1>
        <p v-if="subtitle" class="mt-2 text-center text-sm text-gray-500">
          {{ subtitle }}
        </p>

        <slot />
      </div>
    </div>
  </div>
</template>
