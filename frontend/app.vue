<script setup lang="ts">
const { data: siteSettings } = await useSiteSettings()
const faviconHref = computed(() => siteSettings.value?.favicon_url || '/favicon.ico')

useHead({
  link: [
    { rel: 'icon', type: 'image/x-icon', href: faviconHref },
  ],
})
</script>

<template>
  <div>
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </div>
</template>
