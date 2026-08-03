<script setup lang="ts">
const route = useRoute()

const categories = [
  { to: '/villas', label: 'Villa', icon: 'villa' },
  { to: '/glampings', label: 'Glamping', icon: 'glamping' },
  { to: '/homestays', label: 'Homestay', icon: 'homestay' },
  { to: '/gathering-venues', label: 'Lokasi Gathering', icon: 'gathering' },
  { to: '/transports', label: 'Transport', icon: 'transport' },
] as const

function isActive(to: string) {
  return route.path === to || route.path.startsWith(`${to}/`)
}
</script>

<template>
  <nav class="flex items-center gap-2 overflow-x-auto">
    <NuxtLink
      v-for="category in categories"
      :key="category.to"
      :to="category.to"
      class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition"
      :class="isActive(category.to) ? 'bg-white text-brand-brown-dark' : 'text-white/85 hover:bg-white/10 hover:text-white'"
    >
      <svg
        v-if="category.icon === 'villa'"
        class="h-5 w-5 shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M3 10.5 12 3l9 7.5" />
        <path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5" />
      </svg>
      <svg
        v-else-if="category.icon === 'glamping'"
        class="h-5 w-5 shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M12 3.5 20 20H4L12 3.5Z" />
        <path d="M12 3.5 15.5 20" />
        <path d="M12 3.5 8.5 20" />
        <path d="M9.5 15h5" />
      </svg>
      <svg
        v-else-if="category.icon === 'homestay'"
        class="h-5 w-5 shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M4 21V6.5a1 1 0 0 1 .45-.83l6-4a1 1 0 0 1 1.1 0l6 4a1 1 0 0 1 .45.83V21" />
        <path d="M3 21h18" />
        <path d="M9.5 21v-5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v5" />
        <path d="M9.5 10.5h.01M14.5 10.5h.01" />
      </svg>
      <svg
        v-else-if="category.icon === 'gathering'"
        class="h-5 w-5 shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <circle cx="8.5" cy="8" r="2.5" />
        <circle cx="16" cy="9" r="2" />
        <path d="M3.5 20v-1.5a4 4 0 0 1 4-4h2a4 4 0 0 1 4 4V20" />
        <path d="M14.5 14.2a3.5 3.5 0 0 1 2.8-1.2 3.5 3.5 0 0 1 3.2 3.2V20" />
      </svg>
      <svg
        v-else
        class="h-5 w-5 shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M4.5 16.5 5.8 12a2 2 0 0 1 1.9-1.4h8.6a2 2 0 0 1 1.9 1.4l1.3 4.5" />
        <path d="M3.5 16.5h17V19a1 1 0 0 1-1 1h-1.2a1 1 0 0 1-1-1v-.5H6.7V19a1 1 0 0 1-1 1H4.5a1 1 0 0 1-1-1v-2.5Z" />
        <circle cx="7.5" cy="16.5" r="1.4" />
        <circle cx="16.5" cy="16.5" r="1.4" />
      </svg>
      <span class="whitespace-nowrap">{{ category.label }}</span>
    </NuxtLink>
  </nav>
</template>
