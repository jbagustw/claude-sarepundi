<script setup lang="ts">
const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const { data: siteSettings } = await useSiteSettings()

useHead({
  link: computed(() =>
    siteSettings.value?.favicon_url ? [{ rel: 'icon', href: siteSettings.value.favicon_url }] : []
  ),
})

// The homepage hero has its own full-bleed background image, so the header
// floats transparently on top of it there instead of showing a solid bar —
// until the user scrolls past the hero, at which point it turns solid like
// on every other page. The header is always `fixed` so it stays visible
// while scrolling on every page (not just home).
const isHome = computed(() => route.path === '/')

const scrolled = ref(false)
function onScroll() {
  scrolled.value = window.scrollY > 80
}
onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }))
onUnmounted(() => window.removeEventListener('scroll', onScroll))

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="relative flex min-h-screen flex-col overflow-x-hidden bg-cream">
    <header
      class="fixed inset-x-0 top-0 z-30 w-full transition-colors"
      :class="isHome && !scrolled ? 'bg-transparent' : 'bg-brand-brown-dark shadow-md'"
    >
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <NuxtLink to="/" class="flex shrink-0 items-center gap-2 font-display text-xl font-bold">
          <img
            v-if="siteSettings?.logo_url"
            :src="siteSettings.logo_url"
            class="h-[43px] w-[135px] object-contain object-left"
            alt="sarepundi"
          >
          <template v-else>
            <svg class="h-7 w-7 text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M12 2 3 8v13a1 1 0 001 1h5v-7h6v7h5a1 1 0 001-1V8z" fill="currentColor" />
              <path d="M9 4.5 12 2l3 2.5" stroke="#F1CE33" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span class="text-white">sare<span class="text-brand-gold">pundi</span></span>
          </template>
        </NuxtLink>

        <div class="flex items-center gap-6">
          <nav class="hidden items-center gap-6 sm:flex">
            <NuxtLink to="/berita" class="text-sm font-medium text-white/80 hover:text-white">
              Berita dan Artikel
            </NuxtLink>
            <NuxtLink v-if="!authStore.isAuthenticated" to="/jadi-mitra" class="text-sm font-medium text-white/80 hover:text-white">
              Daftar Sebagai Mitra
            </NuxtLink>
          </nav>

          <div v-if="authStore.isAuthenticated" class="flex items-center gap-3 text-sm">
            <NotificationBell />
            <NuxtLink
              :to="ROLE_HOME[authStore.role as keyof typeof ROLE_HOME]"
              class="hidden text-sm font-medium text-white/80 hover:text-white sm:block"
            >
              Dashboard
            </NuxtLink>
            <span class="hidden items-center gap-1.5 text-white/90 sm:flex">
              {{ authStore.user?.name }}
              <span class="badge bg-white/10 uppercase tracking-wide text-white">
                {{ authStore.role }}
              </span>
            </span>
            <button class="btn-outline !border-white/30 !text-white hover:!bg-white/10 !px-4 !py-1.5" @click="handleLogout">
              Keluar
            </button>
          </div>

          <div v-else class="flex items-center gap-2">
            <NuxtLink to="/login" class="btn-secondary !px-5 !py-2">Masuk</NuxtLink>
            <NuxtLink to="/register" class="btn-accent !px-5 !py-2">Daftar</NuxtLink>
          </div>
        </div>
      </div>

      <div class="border-t border-white/10">
        <div class="mx-auto max-w-6xl px-4 py-2.5">
          <CategoryNav />
        </div>
      </div>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 pb-8" :class="isHome ? 'pt-8' : 'pt-32'">
      <slot />
    </main>

    <footer class="mt-auto bg-brand-footer text-gray-200">
      <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <h3 class="font-display text-sm font-semibold text-white">Jelajahi</h3>
          <ul class="mt-3 space-y-2 text-sm text-gray-300">
            <li><NuxtLink to="/" class="hover:text-white">Beranda</NuxtLink></li>
            <li><NuxtLink to="/villas" class="hover:text-white">Cari Villa</NuxtLink></li>
            <li><NuxtLink to="/homestays" class="hover:text-white">Cari Homestay</NuxtLink></li>
            <li><NuxtLink to="/gathering-venues" class="hover:text-white">Cari Lokasi Gathering</NuxtLink></li>
            <li><NuxtLink to="/transports" class="hover:text-white">Cari Transport</NuxtLink></li>
            <li><NuxtLink to="/berita" class="hover:text-white">Berita dan Artikel</NuxtLink></li>
            <li><NuxtLink to="/jadi-mitra" class="hover:text-white">Daftar Sebagai Mitra</NuxtLink></li>
          </ul>
        </div>
        <div>
          <h3 class="font-display text-sm font-semibold text-white">Bantuan</h3>
          <ul class="mt-3 space-y-2 text-sm text-gray-300">
            <li>Kebijakan Privasi</li>
            <li>Syarat &amp; Ketentuan</li>
          </ul>
        </div>
        <div>
          <h3 class="font-display text-sm font-semibold text-white">Hubungi Kami</h3>
          <ul class="mt-3 space-y-2 text-sm text-gray-300">
            <li>Surabaya, Indonesia</li>
            <li>halo@sarepundi.com</li>
          </ul>
        </div>
        <div>
          <h3 class="font-display text-sm font-semibold text-white">Ikuti Kami</h3>
          <div class="mt-3 flex gap-3">
            <component
              :is="siteSettings?.instagram_url ? 'a' : 'span'"
              :href="siteSettings?.instagram_url || undefined"
              :target="siteSettings?.instagram_url ? '_blank' : undefined"
              :rel="siteSettings?.instagram_url ? 'noopener noreferrer' : undefined"
              :title="siteSettings?.instagram_url ? 'Instagram' : 'Link segera hadir'"
              class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-500 text-gray-300"
              :class="siteSettings?.instagram_url ? 'hover:border-white hover:text-white' : ''"
            >
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="2" width="20" height="20" rx="5" />
                <circle cx="12" cy="12" r="4" />
                <circle cx="17.5" cy="6.5" r="1.1" fill="currentColor" stroke="none" />
              </svg>
              <span class="sr-only">Instagram</span>
            </component>
            <component
              :is="siteSettings?.facebook_url ? 'a' : 'span'"
              :href="siteSettings?.facebook_url || undefined"
              :target="siteSettings?.facebook_url ? '_blank' : undefined"
              :rel="siteSettings?.facebook_url ? 'noopener noreferrer' : undefined"
              :title="siteSettings?.facebook_url ? 'Facebook' : 'Link segera hadir'"
              class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-500 text-gray-300"
              :class="siteSettings?.facebook_url ? 'hover:border-white hover:text-white' : ''"
            >
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M22 12.06C22 6.505 17.523 2 12 2S2 6.505 2 12.06c0 5.02 3.657 9.184 8.438 9.94v-7.03H7.898v-2.91h2.54V9.845c0-2.507 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562v1.875h2.773l-.443 2.91h-2.33V22c4.78-.756 8.437-4.92 8.437-9.94z" />
              </svg>
              <span class="sr-only">Facebook</span>
            </component>
            <component
              :is="siteSettings?.tiktok_url ? 'a' : 'span'"
              :href="siteSettings?.tiktok_url || undefined"
              :target="siteSettings?.tiktok_url ? '_blank' : undefined"
              :rel="siteSettings?.tiktok_url ? 'noopener noreferrer' : undefined"
              :title="siteSettings?.tiktok_url ? 'TikTok' : 'Link segera hadir'"
              class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-500 text-gray-300"
              :class="siteSettings?.tiktok_url ? 'hover:border-white hover:text-white' : ''"
            >
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z" />
              </svg>
              <span class="sr-only">TikTok</span>
            </component>
          </div>
        </div>
      </div>
      <div class="border-t border-white/10 py-4 text-center text-xs text-gray-400">
        Copyright (c) {{ new Date().getFullYear() }} Sarepundi. All rights reserved.
      </div>
    </footer>
  </div>
</template>
