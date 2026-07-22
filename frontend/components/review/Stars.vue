<script setup lang="ts">
defineProps<{
  rating: number
}>()
</script>

<template>
  <span class="text-brand-gold" aria-hidden="true">
    <span v-for="n in 5" :key="n">{{ n <= Math.round(rating) ? '★' : '☆' }}</span>
  </span>
</template>
